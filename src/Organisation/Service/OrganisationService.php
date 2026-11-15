<?php

namespace App\Organisation\Service;

use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\ReceiptNumberGenerator;
use App\Abonnement\Service\SubscriptionManager;
use App\Bilan\Entity\WeeklyReview;
use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Organisation\Entity\Membership;
use App\Organisation\Entity\Organisation;
use App\Organisation\Entity\SeatOrder;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Offre Organisation : création, adhésion, rôles, confidentialité, statistiques, achat de places.
 *
 * Confidentialité (décision du 15/11/2026) : les responsables et accompagnateurs voient des
 * indicateurs d'engagement ; le contenu des plans seulement si le membre l'a accepté ;
 * le journal, jamais.
 */
final class OrganisationService
{
    /** Packs de places proposés => remise de volume. */
    public const PACKS = [10 => 0.0, 25 => 0.10, 50 => 0.15, 100 => 0.25, 250 => 0.35];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
        private readonly Notifier $notifier,
        private readonly ReceiptNumberGenerator $receipts,
    ) {
    }

    // ── Organisation et membres ─────────────────────────────────────────

    public function create(User $owner, string $name, string $type): Organisation
    {
        $org = new Organisation(mb_substr(trim($name), 0, 150), $type, $owner);
        $this->em->persist($org);
        $this->em->persist(new Membership($org, $owner, 'OWNER'));
        $this->em->flush();

        return $org;
    }

    public function membershipOf(User $user, Organisation $org): ?Membership
    {
        return $this->em->getRepository(Membership::class)->findOneBy(['organisation' => $org, 'user' => $user]);
    }

    /** @return Membership[] */
    public function activeMembershipsOf(User $user): array
    {
        return $this->em->getRepository(Membership::class)->findBy(['user' => $user, 'active' => true], ['joinedAt' => 'ASC']);
    }

    /**
     * Rejoindre par le lien d'invitation.
     *
     * @throws \DomainException si l'organisation est complète
     */
    public function join(User $user, Organisation $org, bool $sharePlans): Membership
    {
        $membership = $this->membershipOf($user, $org);
        if ($membership?->isActive()) {
            return $membership->setSharePlans($sharePlans);
        }
        if ($org->isLicenseValid() && !$org->hasFreeSeat()) {
            throw new \DomainException('Toutes les places de cette organisation sont occupées. Prévenez un responsable pour qu\'il en ajoute.');
        }
        if ($membership) {
            $membership->rejoin()->setSharePlans($sharePlans)->setRole('MEMBER');
        } else {
            $membership = (new Membership($org, $user))->setSharePlans($sharePlans);
            $this->em->persist($membership);
        }
        $this->em->flush();

        foreach ($org->getActiveMemberships() as $m) {
            if ($m->isManager() && $m->getUser() !== $user) {
                $this->notifier->notify($m->getUser(), NotificationType::CONNECTION_ACCEPTED, 'Nouveau membre : '.$user->getDisplayName(),
                    $user->getDisplayName().' a rejoint '.$org->getName().' sur KIINI SAWA.', '/organisation', 'membre '.$membership->getId().' '.date('Y-m-d'));
            }
        }

        return $membership;
    }

    public function leave(Membership $membership): void
    {
        if ($membership->isOwner() && 1 === \count(array_filter($membership->getOrganisation()->getActiveMemberships(), fn (Membership $m) => $m->isOwner()))) {
            throw new \DomainException('Vous êtes le seul responsable principal : nommez-en un autre avant de partir.');
        }
        $membership->setActive(false)->setMentor(null);
        foreach ($membership->getOrganisation()->getMemberships() as $m) {
            if ($m->getMentor() === $membership) {
                $m->setMentor(null);
            }
        }
        $this->em->flush();
    }

    // ── Droits ─────────────────────────────────────────────────────────

    /** Le viewer peut-il voir ce membre dans le tableau de bord ? */
    public function canSee(Membership $viewer, Membership $member): bool
    {
        if (!$viewer->isActive() || $viewer->getOrganisation() !== $member->getOrganisation()) {
            return false;
        }

        return $viewer->isManager() || ('MENTOR' === $viewer->getRole() && $member->getMentor() === $viewer) || $viewer === $member;
    }

    /** …et lire le contenu de ses plans ? Seulement avec son consentement. */
    public function canReadPlans(Membership $viewer, Membership $member): bool
    {
        return $viewer !== $member && $member->isActive() && $member->isSharePlans() && $this->canSee($viewer, $member);
    }

    /** @return Membership[] membres visibles par ce viewer */
    public function visibleMembers(Membership $viewer): array
    {
        return array_values(array_filter($viewer->getOrganisation()->getActiveMemberships(), fn (Membership $m) => $this->canSee($viewer, $m)));
    }

    // ── Statistiques (aucun contenu personnel) ─────────────────────────

    public function stats(User $user): array
    {
        $since = new \DateTimeImmutable('-28 days');
        $reviews = $this->em->getRepository(WeeklyReview::class)->createQueryBuilder('w')
            ->select('COUNT(w.id)')->where('w.user = :u')->andWhere('w.weekStart >= :s')
            ->setParameter('u', $user)->setParameter('s', $since)->getQuery()->getSingleScalarResult();

        $daily = array_filter($user->getHabits()->toArray(), fn ($h) => HabitFrequency::DAILY === $h->getFrequency());
        $done = 0;
        $week = new \DateTimeImmutable('-6 days midnight');
        foreach ($daily as $h) {
            foreach ($h->getLogs() as $log) {
                if ($log->isStatus() && $log->getDate() >= $week) {
                    ++$done;
                }
            }
        }
        $plans = array_filter([
            'Vie' => null !== $user->getPlanDeVie()?->getPurpose(),
            'Croissance' => ($user->getPlanDeCroissance()?->getGrowthGoals()->count() ?? 0) > 0,
            'Transmission' => ($user->getPlanDeTransmission()?->getLegacyGoals()->count() ?? 0) > 0,
            'Hygiène' => ($user->getPlanDHygiene()?->getHygieneDomains()->count() ?? 0) > 0,
        ]);

        return [
            'onboarded' => (bool) $user->isOnboardingCompleted(),
            'lastActive' => $user->getLastActiveAt(),
            'activeThisWeek' => null !== $user->getLastActiveAt() && $user->getLastActiveAt() >= $week,
            'plans' => array_keys($plans),
            'reviews4w' => (int) $reviews,
            'habitRate' => [] === $daily ? null : (int) round(min(1, $done / (7 * \count($daily))) * 100),
        ];
    }

    /** Indicateurs d'ensemble pour les responsables. */
    public function summary(array $memberStats): array
    {
        $n = \count($memberStats);
        if (0 === $n) {
            return ['members' => 0, 'onboarded' => 0, 'active' => 0, 'reviewers' => 0];
        }

        return [
            'members' => $n,
            'onboarded' => (int) round(\count(array_filter($memberStats, fn ($s) => $s['onboarded'])) / $n * 100),
            'active' => (int) round(\count(array_filter($memberStats, fn ($s) => $s['activeThisWeek'])) / $n * 100),
            'reviewers' => (int) round(\count(array_filter($memberStats, fn ($s) => $s['reviews4w'] > 0)) / $n * 100),
        ];
    }

    // ── Places ─────────────────────────────────────────────────────────

    public function packPrice(int $seats, string $currency): float
    {
        if (!isset(self::PACKS[$seats])) {
            throw new \InvalidArgumentException('Pack inconnu.');
        }
        $c = $this->entitlements->config();
        $unit = 'XAF' === $currency ? (float) $c->getOrgSeatYearlyPriceXaf() : $c->getOrgSeatYearlyPriceEur();
        $total = $unit * $seats * (1 - self::PACKS[$seats]);

        return 'XAF' === $currency ? round($total, -2) : round($total, 2);
    }

    /** @return array<int, array{seats: int, discount: int, EUR: float, XAF: float}> */
    public function packs(): array
    {
        return array_map(fn (int $seats) => [
            'seats' => $seats,
            'discount' => (int) round(self::PACKS[$seats] * 100),
            'EUR' => $this->packPrice($seats, 'EUR'),
            'XAF' => $this->packPrice($seats, 'XAF'),
        ], array_keys(self::PACKS));
    }

    public function declareOrder(Organisation $org, User $by, int $seats, string $currency, string $method, string $reference): SeatOrder
    {
        if (!isset(SubscriptionManager::CURRENCIES[$currency]) || !\in_array($method, SubscriptionManager::MANUAL_METHODS, true)) {
            throw new \InvalidArgumentException('Devise ou moyen de paiement inconnu.');
        }
        $order = new SeatOrder($org, $by, $seats, $this->packPrice($seats, $currency), $currency, $method, mb_substr($reference, 0, 255) ?: null);
        $this->em->persist($order);
        $this->em->flush();

        $summary = sprintf('%s commande %d places pour %s : %s (%s, réf. %s).', $by->getDisplayName(), $seats, $org->getName(),
            SubscriptionManager::formatAmount($order->getAmount(), $currency), $order->getMethodLabel(), $order->getReference() ?: '—');
        foreach ($this->em->getRepository(User::class)->findAll() as $admin) {
            if (\in_array('ROLE_ADMIN', $admin->getRoles(), true)) {
                $this->notifier->notify($admin, NotificationType::PAYMENT_DECLARED, 'Commande de places à vérifier', $summary, '/admin/paiements', 'places '.$order->getId());
            }
        }

        return $order;
    }

    /** Validation : N places pour un an, à partir de la fin de la licence en cours (ou d'aujourd'hui). */
    public function validateOrder(SeatOrder $order, ?string $note = null): void
    {
        if (!$order->isPending()) {
            return;
        }
        $org = $order->getOrganisation();
        $start = $org->isLicenseValid() ? $org->getSeatsValidUntil() : new \DateTimeImmutable('today');
        $org->setSeats($order->getSeats())->setSeatsValidUntil($start->modify('+1 year'));
        $order->validate($this->receipts->next(), $note);
        $this->em->flush();

        foreach ($org->getActiveMemberships() as $m) {
            if ($m->isManager()) {
                $this->notifier->notify($m->getUser(), NotificationType::PAYMENT_VALIDATED, 'Places activées pour '.$org->getName(),
                    sprintf('%d places sont actives jusqu\'au %s. Partagez le lien d\'invitation : chaque membre devient PRO.', $order->getSeats(), $org->getSeatsValidUntil()->format('d/m/Y')),
                    '/organisation', 'places '.$order->getId());
            }
        }
    }

    public function rejectOrder(SeatOrder $order, string $reason): void
    {
        if (!$order->isPending()) {
            return;
        }
        $order->reject($reason);
        $this->em->flush();
        if ($order->getOrderedBy()) {
            $this->notifier->notify($order->getOrderedBy(), NotificationType::PAYMENT_REJECTED, 'Commande de places non validée',
                'Votre commande n\'a pas pu être validée. Motif : '.$reason, '/organisation', 'places '.$order->getId());
        }
    }

}
