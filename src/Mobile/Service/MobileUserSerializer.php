<?php

namespace App\Mobile\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\PlanDeVie\Service\PlanCatalog;
use App\Rapide\Service\QuickDay;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sérialise l'utilisateur mobile, ses droits d'accès (gratuit / PRO) et le catalogue des 6 plans.
 */
final class MobileUserSerializer
{
    private const PLAN_ICONS = [
        'VIE' => 'compass',
        'CROISSANCE' => 'sprout',
        'DEVELOPPEMENT' => 'wrench',
        'HYGIENE' => 'heart-pulse',
        'INTENDANCE' => 'hand-coins',
        'TRANSMISSION' => 'hand-heart',
    ];

    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly QuickDay $quickDay,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(User $user): array
    {
        $profile = $user->getUserProfile();
        $isPro = $this->entitlements->isPro($user);
        $isTrialing = $this->entitlements->isTrialing($user);
        $sub = $user->getSubscription();
        $personalUntil = $this->entitlements->personalProUntil($user);
        $orgUntil = $this->entitlements->organisationProUntil($user);
        $proUntil = $this->entitlements->proUntil($user);

        $source = 'free';
        if (null !== $orgUntil && (null === $personalUntil || $orgUntil > $personalUntil)) {
            $source = 'organisation';
        } elseif ($isTrialing) {
            $source = 'trial';
        } elseif ($isPro) {
            $source = $sub?->getBillingSource() ?? 'manual';
        }

        $features = [];
        foreach (array_keys(Entitlements::FEATURES) as $featureKey) {
            $features[$featureKey] = $this->entitlements->can($user, $featureKey);
        }

        $locked = $this->entitlements->lockedPlans($user);
        $plansCatalog = [];
        foreach (PlanCatalog::PLANS as $key => $plan) {
            $plansCatalog[] = [
                'key' => $key,
                'label' => $plan['label'],
                'question' => $plan['question'],
                'desc' => $plan['desc'],
                'icon' => self::PLAN_ICONS[$key] ?? 'compass',
                'locked' => \in_array($key, $locked, true),
                'webPath' => $this->urls->generate($plan['route']),
            ];
        }

        return [
            'account' => $this->quickDay->accountKey($user),
            'email' => $user->getEmail(),
            'displayName' => $user->getDisplayName(),
            'fullName' => $user->getFullName(),
            'profile' => [
                'firstName' => $profile?->getFirstName(),
                'lastName' => $profile?->getLastName(),
                'city' => $profile?->getCity(),
                'country' => $profile?->getCountry(),
                'profession' => $profile?->getProfession(),
                'bio' => $profile?->getBio(),
                // Privée : renvoyée seulement à la personne elle-même (cet objet ne sert qu'à /moi, /connexion et /jeton)
                'dateOfBirth' => $profile?->getDateOfBirth()?->format('Y-m-d'),
                'askBirthDate' => null === $profile || $profile->shouldAskBirthDate(),
            ],
            'entitlements' => [
                'isPro' => $isPro,
                'isTrialing' => $isTrialing,
                'tier' => $sub?->getTier()->value ?? 'FREE',
                'source' => $source,
                'proUntil' => $proUntil?->format(\DATE_ATOM),
                'features' => $features,
                'limits' => [
                    'habits' => $this->entitlements->limit($user, 'habits'),
                    'connections' => $this->entitlements->limit($user, 'connections'),
                ],
                'freeTests' => $this->entitlements->freeTests(),
            ],
            'plansCatalog' => $plansCatalog,
        ];
    }
}
