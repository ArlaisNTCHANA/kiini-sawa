<?php

namespace App\Shared\Twig;

use App\PlanDeVie\Service\PlanCatalog;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Vocabulaire unique de l'interface (voir docs/lexique.md).
 * Les catégories d'objectifs sont stockées en anglais (BE, DO, HAVE, GIVE) mais toujours affichées en français.
 * Les clés des plans (VIE, HYGIENE…) s'affichent avec leur vrai nom (filtre nom_plan, PlanCatalog::PLANS).
 */
final class LexiconExtension extends AbstractExtension
{
    public const GOAL_CATEGORIES = ['BE' => 'Être', 'DO' => 'Faire', 'HAVE' => 'Avoir', 'GIVE' => 'Donner'];

    public function getFilters(): array
    {
        return [
            new TwigFilter('categorie_objectif', fn (?string $code): string => self::GOAL_CATEGORIES[strtoupper((string) $code)] ?? (string) $code),
            new TwigFilter('nom_plan', fn (?string $key): string => PlanCatalog::PLANS[strtoupper((string) $key)]['label'] ?? (string) $key),
        ];
    }
}
