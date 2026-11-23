<?php

namespace App\Social\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\PlanDeVie\Entity\LifeGoal;
use App\Rituels\Entity\Habit;
use App\Social\Entity\AccountabilityCommitment;
use App\Social\Entity\AccountabilityNudge;
use App\Social\Entity\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Engagements de redevabilité (lot 1) : la personne choisit quoi, qui et accepte ce qui sera envoyé ; le partenaire
 * accepte son rôle ; la personne demande à être interpellée ; le partenaire répond en un clic. Voir docs/modules/social.md.
 */
final class AccountabilityService
{
    /** Engagements ouverts permis avec l'offre gratuite (illimité en PRO). */
    public const FREE_LIMIT = 1;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
        private readonly Notifier $notifier,
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urls,
        #[Autowire('%app.mailer_from%')] private readonly string $from,
    ) {
    }

    /** @return list<AccountabilityCommitment> */
    public function commitmentsOf(User $owner): array
    {
        return $this->em->getRepository(AccountabilityCommitment::class)->findBy(['owner' => $owner], ['createdAt' => 'DESC']);
    }

    /** @return list<AccountabilityCommitment> engagements où cet utilisateur est le partenaire (s'il a un compte) */
    public function commitmentsAsPartner(User $user): array
    {
        return $this->em->getRepository(AccountabilityCommitment::class)->findBy(['partnerUser' => $user, 'status' => AccountabilityCommitment::STATUS_ACTIVE], ['createdAt' => 'DESC']);
    }

    public function canCreate(User $owner): bool
    {
        if ($this->entitlements->isPro($owner)) {
            return true;
        }

        return \count(array_filter($this->commitmentsOf($owner), static fn (AccountabilityCommitment $c) => $c->isOpen())) < self::FREE_LIMIT;
    }

    /**
     * Objectifs du Plan de Vie et rituels de la personne, proposés comme objets d'engagement.
     *
     * @return array{goals: list<array{id: int, label: string}>, habits: list<array{id: int, label: string}>}
     */
    public function targetsFor(User $owner): array
    {
        $goals = [];
        foreach ($owner->getPlanDeVie()?->getLifeGoals() ?? [] as $goal) {
            if ($goal instanceof LifeGoal && $goal->getId() && '' !== trim((string) $goal->getTitle())) {
                $goals[] = ['id' => $goal->getId(), 'label' => (string) $goal->getTitle()];
            }
        }
        $habits = [];
        foreach ($owner->getHabits() as $habit) {
            if ($habit instanceof Habit && $habit->getId() && '' !== trim((string) $habit->getTitle())) {
                $habits[] = ['id' => $habit->getId(), 'label' => (string) $habit->getTitle()];
            }
        }

        return ['goals' => $goals, 'habits' => $habits];
    }

    /** @return list<array{id: int, name: string, email: string}> partenaires acceptés (comptes) */
    public function partnersOf(User $owner): array
    {
        $out = [];
        foreach ($this->em->getRepository(Connection::class)->findBy(['status' => 'ACCEPTED', 'requester' => $owner]) as $c) {
            $out[] = ['id' => $c->getId(), 'name' => $c->getReceiver()->getDisplayName(), 'email' => (string) $c->getReceiver()->getEmail()];
        }
        foreach ($this->em->getRepository(Connection::class)->findBy(['status' => 'ACCEPTED', 'receiver' => $owner]) as $c) {
            $out[] = ['id' => $c->getId(), 'name' => $c->getRequester()->getDisplayName(), 'email' => (string) $c->getRequester()->getEmail()];
        }

        return $out;
    }

    /**
     * Crée l'engagement après le consentement explicite de la personne, puis invite le partenaire à accepter son rôle.
     *
     * @param array{target?: string, freeLabel?: string, why?: string, connectionId?: int|string|null, partnerName?: string, partnerEmail?: string, consent?: bool} $data
     */
    public function create(User $owner, array $data): AccountabilityCommitment
    {
        if (true !== ($data['consent'] ?? false)) {
            throw new \InvalidArgumentException('Merci de confirmer que vous avez compris ce qui sera envoyé à votre partenaire.');
        }
        if (!$this->canCreate($owner)) {
            throw new \DomainException('L\'offre gratuite comprend un engagement de redevabilité. Passez à PRO pour en ouvrir d\'autres, ou arrêtez celui en cours.');
        }

        [$type, $id, $label] = $this->resolveTarget($owner, (string) ($data['target'] ?? ''), (string) ($data['freeLabel'] ?? ''));

        $partnerUser = null;
        $connectionId = (int) ($data['connectionId'] ?? 0);
        if ($connectionId > 0) {
            $connection = $this->em->find(Connection::class, $connectionId);
            if (!$connection instanceof Connection || 'ACCEPTED' !== $connection->getStatus() || ($connection->getRequester() !== $owner && $connection->getReceiver() !== $owner)) {
                throw new \InvalidArgumentException('Partenaire introuvable.');
            }
            $partnerUser = $connection->getRequester() === $owner ? $connection->getReceiver() : $connection->getRequester();
            $name = $partnerUser->getDisplayName();
            $email = (string) $partnerUser->getEmail();
        } else {
            $name = trim((string) ($data['partnerName'] ?? ''));
            $email = mb_strtolower(trim((string) ($data['partnerEmail'] ?? '')));
            if ('' === $name || !filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
                throw new \InvalidArgumentException('Indiquez le prénom et une adresse e-mail valide de votre partenaire.');
            }
            $partnerUser = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        }
        if (mb_strtolower($email) === mb_strtolower((string) $owner->getEmail())) {
            throw new \InvalidArgumentException('Choisissez une autre personne que vous-même.');
        }

        $commitment = new AccountabilityCommitment($owner, $name, $email, $type, $id, $label, $data['why'] ?? null);
        $commitment->setPartnerUser($partnerUser);
        $this->em->persist($commitment);
        $this->em->flush();

        $this->mail($commitment->getPartnerEmail(), $owner->getDisplayName().' vous demande d\'être son partenaire de redevabilité', 'social/accountability/email/invitation.html.twig', [
            'c' => $commitment,
            'owner' => $owner->getDisplayName(),
            'url' => $this->urls->generate('app_accountability_partner', ['token' => $commitment->getPartnerToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $commitment;
    }

    public function findByPartnerToken(string $token): ?AccountabilityCommitment
    {
        return $this->em->getRepository(AccountabilityCommitment::class)->findOneBy(['partnerToken' => $token]);
    }

    public function findNudge(string $token): ?AccountabilityNudge
    {
        return $this->em->getRepository(AccountabilityNudge::class)->findOneBy(['token' => $token]);
    }

    public function accept(AccountabilityCommitment $c): void
    {
        if (!$c->isPending()) {
            return;
        }
        $c->accept();
        $this->em->flush();
        $this->notifier->notify($c->getOwner(), NotificationType::ACCOUNTABILITY, $c->getPartnerName().' a accepté d\'être votre partenaire de redevabilité',
            'Pour « '.$c->getTargetLabel().' ». Vous pouvez maintenant lui demander de vous interpeller.', '/social/redevabilite', 'accountability-accepted-'.$c->getId());
    }

    public function decline(AccountabilityCommitment $c): void
    {
        if (!$c->isPending()) {
            return;
        }
        $c->decline();
        $this->em->flush();
        $this->notifier->notify($c->getOwner(), NotificationType::ACCOUNTABILITY, $c->getPartnerName().' n\'a pas pu accepter ce rôle',
            'Pour « '.$c->getTargetLabel().' ». Vous pouvez choisir une autre personne.', '/social/redevabilite', 'accountability-declined-'.$c->getId());
    }

    /** Le partenaire ne souhaite plus recevoir de messages : l'engagement s'arrête. */
    public function partnerStops(AccountabilityCommitment $c): void
    {
        if (!$c->isOpen()) {
            return;
        }
        $c->stop('PARTNER');
        $this->em->flush();
        $this->notifier->notify($c->getOwner(), NotificationType::ACCOUNTABILITY, $c->getPartnerName().' a mis fin à son rôle de partenaire',
            'Pour « '.$c->getTargetLabel().' ». Merci pour ce chemin parcouru ensemble.', '/social/redevabilite', 'accountability-partner-stopped-'.$c->getId());
    }

    public function ownerAction(AccountabilityCommitment $c, User $owner, string $action): void
    {
        if ($c->getOwner() !== $owner) {
            throw new \DomainException('Engagement introuvable.');
        }
        match ($action) {
            'pause' => $c->isActive() ? $c->pause() : null,
            'resume' => $c->isPaused() ? $c->resume() : null,
            'stop' => $c->isOpen() ? $c->stop('OWNER') : null,
            default => throw new \InvalidArgumentException('Action inconnue.'),
        };
        $this->em->flush();
    }

    /** Date à partir de laquelle une nouvelle interpellation est possible (null : tout de suite). */
    public function nextNudgeAllowedAt(AccountabilityCommitment $c): ?\DateTimeImmutable
    {
        $last = $this->em->getRepository(AccountabilityNudge::class)->findOneBy(['commitment' => $c], ['sentAt' => 'DESC']);
        if (null === $last) {
            return null;
        }
        $next = $last->getSentAt()->modify('+'.$c->getMinDaysBetweenNudges().' days');

        return $next > new \DateTimeImmutable() ? $next : null;
    }

    /**
     * La personne demande à son partenaire de l'interpeller. L'e-mail part au partenaire, la copie à la personne.
     */
    public function requestNudge(AccountabilityCommitment $c, User $owner, ?string $message): AccountabilityNudge
    {
        if ($c->getOwner() !== $owner) {
            throw new \DomainException('Engagement introuvable.');
        }
        if (!$c->isActive()) {
            throw new \DomainException($c->isPending() ? 'Votre partenaire n\'a pas encore accepté son rôle.' : 'Cet engagement n\'est pas actif.');
        }
        $next = $this->nextNudgeAllowedAt($c);
        if (null !== $next) {
            throw new \DomainException('Votre partenaire a déjà été interpellé récemment. Prochaine demande possible le '.$next->format('d/m/Y').'.');
        }

        $nudge = new AccountabilityNudge($c, AccountabilityNudge::REASON_OWNER_REQUEST, $message);
        $this->em->persist($nudge);
        $this->em->flush();

        $context = [
            'c' => $c,
            'nudge' => $nudge,
            'owner' => $owner->getDisplayName(),
            'actions' => AccountabilityNudge::ACTIONS,
            'respondUrl' => $this->urls->generate('app_accountability_respond', ['token' => $nudge->getToken()], UrlGeneratorInterface::ABSOLUTE_URL),
            'stopUrl' => $this->urls->generate('app_accountability_partner', ['token' => $c->getPartnerToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ];
        $this->mail($c->getPartnerEmail(), $owner->getDisplayName().' compte sur vous', 'social/accountability/email/nudge.html.twig', $context);
        $this->mail((string) $owner->getEmail(), 'Copie : votre partenaire '.$c->getPartnerName().' a été interpellé', 'social/accountability/email/nudge_copy.html.twig', $context + [
            'manageUrl' => $this->urls->generate('app_accountability', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        return $nudge;
    }

    /** Le partenaire indique ce qu'il a fait (il peut corriger sa réponse). */
    public function respond(AccountabilityNudge $nudge, string $action, ?string $note): void
    {
        if (!isset(AccountabilityNudge::ACTIONS[$action])) {
            throw new \InvalidArgumentException('Choisissez ce que vous avez fait.');
        }
        $first = null === $nudge->getRespondedAt();
        $nudge->respond($action, $note);
        $this->em->flush();

        $c = $nudge->getCommitment();
        if ($first) {
            $this->notifier->notify($c->getOwner(), NotificationType::ACCOUNTABILITY, $c->getPartnerName().' a répondu présent',
                AccountabilityNudge::ACTIONS[$action].($nudge->getResponseNote() ? ' : « '.$nudge->getResponseNote().' »' : '.'), '/social/redevabilite', 'accountability-response-'.$nudge->getId());
        }
    }

    /**
     * Chiffres de l'engagement (lot 1 : interpellations, réponses, délai ; l'effet sur l'objectif viendra au lot 2).
     *
     * @return array{nudges: int, responses: int, rate: int|null, avgHours: int|null, byAction: array<string, int>, last: list<AccountabilityNudge>}
     */
    public function stats(AccountabilityCommitment $c): array
    {
        $nudges = $this->em->getRepository(AccountabilityNudge::class)->findBy(['commitment' => $c], ['sentAt' => 'DESC']);
        $responded = array_filter($nudges, static fn (AccountabilityNudge $n) => null !== $n->getRespondedAt());
        $byAction = [];
        $hours = [];
        foreach ($responded as $n) {
            $byAction[$n->getResponseAction()] = ($byAction[$n->getResponseAction()] ?? 0) + 1;
            $hours[] = ($n->getRespondedAt()->getTimestamp() - $n->getSentAt()->getTimestamp()) / 3600;
        }

        return [
            'nudges' => \count($nudges),
            'responses' => \count($responded),
            'rate' => \count($nudges) > 0 ? (int) round(100 * \count($responded) / \count($nudges)) : null,
            'avgHours' => $hours ? (int) round(array_sum($hours) / \count($hours)) : null,
            'byAction' => $byAction,
            'last' => \array_slice($nudges, 0, 5),
        ];
    }

    /** @return array{0: string, 1: int|null, 2: string} */
    private function resolveTarget(User $owner, string $target, string $freeLabel): array
    {
        if (preg_match('/^(GOAL|HABIT):(\d+)$/', $target, $m)) {
            $id = (int) $m[2];
            if ('GOAL' === $m[1]) {
                $goal = $this->em->find(LifeGoal::class, $id);
                if ($goal instanceof LifeGoal && $goal->getPlanDeVie()?->getUser() === $owner) {
                    return [AccountabilityCommitment::TARGET_GOAL, $id, (string) $goal->getTitle()];
                }
            } else {
                $habit = $this->em->find(Habit::class, $id);
                if ($habit instanceof Habit && $habit->getUser() === $owner) {
                    return [AccountabilityCommitment::TARGET_HABIT, $id, (string) $habit->getTitle()];
                }
            }
            throw new \InvalidArgumentException('Objectif ou rituel introuvable.');
        }
        $label = trim($freeLabel);
        if ('' === $label) {
            throw new \InvalidArgumentException('Choisissez un objectif, un rituel, ou écrivez votre engagement.');
        }

        return [AccountabilityCommitment::TARGET_FREE, null, $label];
    }

    /** @param array<string, mixed> $context */
    private function mail(string $to, string $subject, string $template, array $context): void
    {
        $this->mailer->send((new TemplatedEmail())->from(Address::create($this->from))->to($to)
            ->subject($subject)->htmlTemplate($template)->context($context));
    }
}
