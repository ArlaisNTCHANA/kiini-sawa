<?php

namespace App\Abonnement\Twig;

use App\Abonnement\Service\Entitlements;
use App\Abonnement\Service\SubscriptionManager;
use App\Compte\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Dans les gabarits :
 * - {{ is_pro() }}, {{ can('map') }}, {{ pro_until() }} : droits de l'utilisateur connecté ;
 * - {{ montant|money('XAF') }} : « 25 000 FCFA » ou « 79,99 € ».
 */
final class AbonnementExtension extends AbstractExtension
{
    public function __construct(private readonly Entitlements $entitlements, private readonly Security $security)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_pro', fn (): bool => $this->entitlements->isPro($this->user())),
            new TwigFunction('can', fn (string $feature): bool => $this->entitlements->can($this->user(), $feature)),
            new TwigFunction('is_trialing', fn (): bool => ($u = $this->user()) ? $this->entitlements->isTrialing($u) : false),
            new TwigFunction('pro_until', fn (): ?\DateTimeInterface => ($u = $this->user()) ? $this->entitlements->proUntil($u) : null),
        ];
    }

    public function getFilters(): array
    {
        return [new TwigFilter('money', fn (float|int|null $amount, string $currency): string => SubscriptionManager::formatAmount((float) $amount, $currency))];
    }

    private function user(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
