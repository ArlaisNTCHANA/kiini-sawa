<?php

namespace App\Notification\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Notification\Entity\Announcement;
use App\Notification\Enum\NotificationType;
use App\Organisation\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Envoie une annonce de l'équipe (Admin › Écrire aux membres) : notification dans l'application, et e-mail si
 * l'administrateur l'a choisi et que le membre n'a pas coupé « Les annonces de l'équipe ».
 *
 * Par lots, pour tenir sur l'hébergement mutualisé (pas de processus permanent) : la page d'administration enchaîne les
 * lots, et la tâche planifiée termine un envoi interrompu (`app:annonces:envoyer`). La clé « annonce-<id> » du Notifier
 * empêche tout doublon. Jamais aux administrateurs ni aux comptes dont l'adresse n'est pas vérifiée.
 */
final class AnnouncementSender
{
    public const BATCH = 25;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
        private readonly NotificationMailer $mailer,
        private readonly Entitlements $entitlements,
    ) {
    }

    /** Le membre fait-il partie du public visé ? */
    public function matches(Announcement $a, User $user): bool
    {
        if (!$user->isVerified() || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }
        // « Les annonces de l'équipe » coupées dans le profil : rien, ni e-mail ni notification
        if (false === $user->getNotificationPreference()?->isNotifyAnnouncements()) {
            return false;
        }

        return match ($a->getAudience()) {
            'FREE' => !$this->entitlements->isPro($user),
            'TRIAL' => $this->entitlements->isTrialing($user),
            'PRO' => $this->entitlements->isPro($user) && !$this->entitlements->isTrialing($user),
            'INACTIVE' => null === $user->getLastActiveAt() || $user->getLastActiveAt() < new \DateTimeImmutable('-30 days'),
            'ORGANISATION' => null !== $a->getOrganisationId() && null !== $this->em->getRepository(Membership::class)->findOneBy(['user' => $user, 'organisation' => $a->getOrganisationId(), 'active' => true]),
            default => true,
        };
    }

    /** Nombre de membres visés (aperçu avant envoi). */
    public function count(Announcement $a): int
    {
        $n = 0;
        $after = 0;
        while ([] !== ($users = $this->users($a, $after, 200))) {
            foreach ($users as $u) {
                $n += (int) $this->matches($a, $u);
                $after = (int) $u->getId();
            }
        }

        return $n;
    }

    /**
     * Un lot : au plus BATCH membres prévenus. Renvoie true quand l'annonce est entièrement envoyée.
     */
    public function sendBatch(Announcement $a): bool
    {
        if (Announcement::STATUS_SENT === $a->getStatus()) {
            return true;
        }
        $a->setStatus(Announcement::STATUS_SENDING);
        $sent = 0;
        while ($sent < self::BATCH) {
            $users = $this->users($a, $a->getCursor(), self::BATCH);
            if ([] === $users) {
                $a->setStatus(Announcement::STATUS_SENT)->setSentAt(new \DateTimeImmutable());
                break;
            }
            foreach ($users as $u) {
                $a->setCursor((int) $u->getId());
                if ($this->matches($a, $u) && $this->notifier->notify($u, NotificationType::ANNOUNCEMENT, $a->getTitle(), $a->getMessage(), $a->getActionUrl(), $a->key(), $this->email($a))) {
                    $a->addReached(1);
                    ++$sent;
                    if ($sent >= self::BATCH) {
                        break;
                    }
                }
            }
        }
        $this->em->flush();

        return Announcement::STATUS_SENT === $a->getStatus();
    }

    /** Un essai, seulement à l'administrateur, par e-mail (sans notification ni trace d'envoi). */
    public function sendTest(Announcement $a, User $admin): void
    {
        $this->mailer->send($admin, NotificationType::ANNOUNCEMENT, '[Essai] '.$a->getTitle(), $a->getMessage(), $a->getActionUrl(),
            'notification/email/announcement.html.twig', $this->email($a)['context']);
    }

    /** @return array{template: string, context: array<string, mixed>, send: bool} */
    private function email(Announcement $a): array
    {
        return ['template' => 'notification/email/announcement.html.twig', 'context' => ['actionLabel' => $a->getActionLabel() ?: 'Ouvrir KIINI SAWA'], 'send' => $a->isWithEmail()];
    }

    /**
     * Candidats dans l'ordre des identifiants, déjà filtrés en base (adresse vérifiée, pas administrateur, et pour une
     * organisation ou une absence, le critère lui-même) : la page ne parcourt pas tous les comptes un par un.
     * `matches()` reste l'arbitre final (offre gratuite, essai, PRO).
     *
     * @return list<User>
     */
    private function users(Announcement $a, int $after, int $limit): array
    {
        $qb = $this->em->getRepository(User::class)->createQueryBuilder('u')
            ->leftJoin('u.subscription', 's')->addSelect('s')
            ->where('u.id > :after')->setParameter('after', $after)
            ->andWhere('u.isVerified = true')
            ->andWhere('u.roles NOT LIKE :admin')->setParameter('admin', '%ROLE_ADMIN%')
            ->orderBy('u.id', 'ASC')->setMaxResults($limit);
        if ('ORGANISATION' === $a->getAudience()) {
            $qb->andWhere('EXISTS (SELECT 1 FROM '.Membership::class.' m WHERE m.user = u AND m.organisation = :org AND m.active = true)')
                ->setParameter('org', (int) $a->getOrganisationId());
        }
        if ('INACTIVE' === $a->getAudience()) {
            $qb->andWhere('u.lastActiveAt IS NULL OR u.lastActiveAt < :since')->setParameter('since', new \DateTimeImmutable('-30 days'));
        }

        return $qb->getQuery()->getResult();
    }
}
