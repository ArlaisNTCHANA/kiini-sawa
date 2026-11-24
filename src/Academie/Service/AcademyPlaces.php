<?php

namespace App\Academie\Service;

use App\PlanDeVie\Service\PlanCatalog;

/**
 * Où se rattache un parcours : un des 6 plans (ordre et noms de PlanCatalog) ou, à part, le Sanctuaire du Couple.
 * Fournit aussi l'illustration d'un parcours (icône et couleurs du plan) : pas d'image envoyée en V1.
 */
final class AcademyPlaces
{
    public const COUPLE = 'COUPLE';

    private const LOOK = [
        'VIE' => ['icon' => 'compass', 'color' => 'plan-vie'],
        'CROISSANCE' => ['icon' => 'sprout', 'color' => 'plan-croissance'],
        'DEVELOPPEMENT' => ['icon' => 'wrench', 'color' => 'plan-developpement'],
        'HYGIENE' => ['icon' => 'heart-pulse', 'color' => 'plan-hygiene'],
        'INTENDANCE' => ['icon' => 'hand-coins', 'color' => 'plan-intendance'],
        'TRANSMISSION' => ['icon' => 'hand-heart', 'color' => 'plan-transmission'],
        self::COUPLE => ['icon' => 'heart-handshake', 'color' => 'couple'],
    ];

    /** @return array<string, string> les 6 plans : clé => nom affiché, dans l'ordre du livre. */
    public static function plans(): array
    {
        return array_map(fn (array $p) => $p['label'], PlanCatalog::PLANS);
    }

    public static function isValid(?string $place): bool
    {
        return null === $place || self::COUPLE === $place || isset(PlanCatalog::PLANS[$place]);
    }

    public static function label(?string $place): string
    {
        return match (true) {
            null === $place => 'Sans plan précis',
            self::COUPLE === $place => 'Sanctuaire du Couple',
            default => PlanCatalog::PLANS[$place]['label'] ?? 'Sans plan précis',
        };
    }

    /** @return array{icon: string, color: string, soft: string} icône Lucide et variables CSS de couleur. */
    public static function look(?string $place): array
    {
        $look = self::LOOK[$place ?? ''] ?? ['icon' => 'graduation-cap', 'color' => 'primary'];

        return ['icon' => $look['icon'], 'color' => 'var(--'.$look['color'].')', 'soft' => 'var(--'.$look['color'].'-soft)'];
    }
}
