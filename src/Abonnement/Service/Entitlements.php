<?php

namespace App\Abonnement\Service;

use App\Abonnement\Entity\Subscription;
use App\Abonnement\Enum\SubscriptionStatus;
use App\Abonnement\Enum\SubscriptionTier;
use App\Abonnement\Exception\ProRequiredException;
use App\Admin\Entity\AppConfig;
use App\Compte\Entity\User;
use App\Organisation\Entity\Membership;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Droits d'accès selon l'offre : qui est PRO, quelles fonctions, quelles limites.
 *
 * Tout se règle dans l'administration (Configuration) : prix, durée d'essai, limites gratuites
 * et interrupteurs « gratuit / PRO » par fonction. Un administrateur a toujours tout.
 *
 * Est PRO : un abonnement PRO ou TEAM dont l'essai ou la période payée n'est pas terminé.
 * Les paiements manuels ne se renouvellent pas : à la fin de la période, l'utilisateur
 * repasse automatiquement en gratuit, sans perdre ses données.
 */
final class Entitlements
{
    /** Fonction => interrupteur « gratuit » de la configuration. */
    public const FEATURES = [
        'croissance' => ['isEnableGrowthFree', 'Plan de Croissance'],
        'developpement' => ['isEnableDevelopmentFree', 'Plan de Développement'],
        'transmission' => ['isEnableTransmissionFree', 'Plan de Transmission'],
        'hygiene' => ['isEnableHygieneFree', 'Plan d\'Hygiène de Vie'],
        'map' => ['isEnableMapFree', 'Carte stratégique'],
        'pdf' => ['isEnablePrintFree', 'Export PDF'],
        'monthly_review' => ['isEnableMonthlyReviewsFree', 'Revue mensuelle'],
        'plan_history' => ['isEnablePlanHistoryFree', 'Historique des plans'],
        'coherence' => ['isEnableCoherenceAnalysisFree', 'Analyse de cohérence'],
        'journal' => ['isEnableJournalFree', 'Journal'],
        'habits' => ['isEnableHabitsFree', 'Rituels'],
        'planner' => ['isEnablePlannerFree', 'Agenda'],
        'social' => ['isEnableSocialFree', 'Partenaires'],
        'discovery' => ['isEnableDiscoveryFree', 'Tests'],
        'method' => ['isEnableMethodFree', 'La méthode'],
    ];

    /** Limites de l'offre gratuite => getter de la configuration. */
    public const LIMITS = [
        'habits' => 'getMaxHabitsFree',
        'connections' => 'getMaxConnectionsFree',
    ];


    /** Plan stratégique (clé du catalogue) => fonction. */
    public const PLAN_FEATURES = [
        'CROISSANCE' => 'croissance',
        'DEVELOPPEMENT' => 'developpement',
        'TRANSMISSION' => 'transmission',
        'HYGIENE' => 'hygiene',
    ];

    private ?AppConfig $config = null;

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function config(): AppConfig
    {
        if (null === $this->config) {
            $this->config = $this->em->getRepository(AppConfig::class)->find('default');
            if (null === $this->config) {
                $this->config = (new AppConfig());
                $this->em->persist($this->config);
                $this->em->flush();
            }
        }

        return $this->config;
    }

    public function isPro(?User $user): bool
    {
        if (null === $user) {
            return false;
        }
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return null !== $this->proUntil($user);
    }

    /**
     * Date de fin des droits PRO, ou null si l'utilisateur est en gratuit.
     * Le plus favorable de : son essai ou sa période payée, ou une place dans une organisation valide.
     */
    public function proUntil(User $user): ?\DateTimeInterface
    {
        $personal = $this->personalProUntil($user);
        $org = $this->organisationProUntil($user);

        return match (true) {
            null === $personal => $org,
            null === $org => $personal,
            default => $org > $personal ? $org : $personal,
        };
    }

    /** Fin des droits apportés par une organisation (place active, licence valide). */
    public function organisationProUntil(User $user): ?\DateTimeInterface
    {
        if (null === $user->getId()) {
            return null;
        }
        $best = null;
        foreach ($this->em->getRepository(Membership::class)->findBy(['user' => $user, 'active' => true]) as $m) {
            $org = $m->getOrganisation();
            if ($org->isLicenseValid()) {
                $end = $org->getSeatsValidUntil()->setTime(23, 59, 59);
                $best = null === $best || $end > $best ? $end : $best;
            }
        }

        return $best;
    }

    public function personalProUntil(User $user): ?\DateTimeInterface
    {
        $sub = $user->getSubscription();
        if (null === $sub || SubscriptionTier::FREE === $sub->getTier()) {
            return null;
        }
        $now = new \DateTimeImmutable();
        $end = SubscriptionStatus::TRIAL === $sub->getStatus() ? $sub->getTrialEndAt() : $sub->getCurrentPeriodEnd();
        if (\in_array($sub->getStatus(), [SubscriptionStatus::CANCELED, SubscriptionStatus::EXPIRED], true) && (null === $end || $end <= $now)) {
            return null;
        }

        return null !== $end && $end > $now ? $end : null;
    }

    public function isTrialing(User $user): bool
    {
        $sub = $user->getSubscription();

        return null !== $sub && SubscriptionStatus::TRIAL === $sub->getStatus() && null !== $this->personalProUntil($user) && null === $this->organisationProUntil($user);
    }

    public function can(?User $user, string $feature): bool
    {
        if ($this->isPro($user)) {
            return true;
        }
        $getter = self::FEATURES[$feature][0] ?? null;

        return null !== $getter && (bool) $this->config()->{$getter}();
    }

    /** Limite de l'offre gratuite, ou null si illimité (PRO). */
    public function limit(?User $user, string $key): ?int
    {
        if ($this->isPro($user) || !isset(self::LIMITS[$key])) {
            return null;
        }

        return (int) $this->config()->{self::LIMITS[$key]}();
    }

    /** @throws ProRequiredException si la fonction est réservée à PRO */
    public function require(?User $user, string $feature): void
    {
        if (!$this->can($user, $feature)) {
            throw new ProRequiredException($feature, '« '.self::featureLabel($feature).' » fait partie de KIINI SAWA PRO.');
        }
    }

    /** @throws ProRequiredException si la limite gratuite est atteinte */
    public function requireBelowLimit(?User $user, string $key, int $current, string $message): void
    {
        $limit = $this->limit($user, $key);
        if (null !== $limit && $current >= $limit) {
            throw new ProRequiredException($key, $message);
        }
    }

    /** Plans stratégiques (clés du catalogue) non accessibles à cet utilisateur. */
    public function lockedPlans(?User $user): array
    {
        return array_keys(array_filter(self::PLAN_FEATURES, fn (string $f) => !$this->can($user, $f)));
    }

    public static function featureLabel(string $feature): string
    {
        return self::FEATURES[$feature][1] ?? ['connections' => 'Partenaires illimités'][$feature] ?? 'Cette fonction';
    }

    /** Ouvre l'essai PRO (une seule fois par compte). */
    public function startTrial(User $user): void
    {
        $sub = $user->getSubscription() ?? (new Subscription())->setUser($user);
        if ($sub->isTrialUsed()) {
            return;
        }
        $days = max(0, $this->config()->getTrialDurationDays());
        if (0 === $days) {
            return;
        }
        $now = new \DateTime();
        $sub->setTier(SubscriptionTier::PRO)->setStatus(SubscriptionStatus::TRIAL)
            ->setTrialStartAt($now)->setTrialEndAt((clone $now)->modify("+$days days"))->setIsTrialUsed(true);
        $user->setSubscription($sub);
        $this->em->persist($sub);
    }
}
