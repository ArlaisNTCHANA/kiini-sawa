<?php

namespace App\Mobile\Service;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Methode\Service\BookLibrary;

/**
 * Sérialisation des chapitres du livre KIINI SAWA pour l'application mobile.
 *
 * Respecte les droits d'accès :
 * - Les premiers chapitres (publics) sont lisibles en entier par tous.
 * - Pour les utilisateurs PRO ou ayant la méthode activée : texte intégral (HTML).
 * - Pour les utilisateurs gratuits : extrait pratique « L'essentiel » et drapeau isProRequired.
 * - Zéro mention de prix ou de paiement (règle Apple 3.1.1).
 */
final class MobileMethodeSerializer
{
    public function __construct(
        private readonly BookLibrary $library,
        private readonly Entitlements $entitlements,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCatalog(User $user): array
    {
        $canFull = $this->entitlements->can($user, 'method');
        $chapters = [];
        $order = 1;

        foreach ($this->library->all() as $ch) {
            $isPublic = $this->library->isPublic($ch['slug']);
            $chapters[] = [
                'order' => $order++,
                'slug' => $ch['slug'],
                'title' => $ch['title'],
                'excerpt' => $ch['excerpt'],
                'minutes' => $ch['minutes'],
                'isPublic' => $isPublic,
                'isProRequired' => !$isPublic && !$canFull,
            ];
        }

        return [
            'total' => \count($chapters),
            'hasFullAccess' => $canFull,
            'chapters' => $chapters,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function serializeChapter(string $slug, User $user): ?array
    {
        $data = $this->library->get($slug);
        if (null === $data) {
            return null;
        }

        $isPublic = $this->library->isPublic($slug);
        $canFull = $this->entitlements->can($user, 'method');
        $hasFullAccess = $isPublic || $canFull;

        return [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'excerpt' => $data['excerpt'],
            'minutes' => $data['minutes'],
            'previous' => $data['previous'],
            'next' => $data['next'],
            'isPublic' => $isPublic,
            'isProRequired' => !$hasFullAccess,
            'hasFullAccess' => $hasFullAccess,
            // Si accès complet, on transmet le texte intégral en HTML.
            // Sinon, on transmet l'essentiel (extraits pratiques, sanctuaire).
            'contentHtml' => $hasFullAccess ? $data['html'] : $data['essentials'],
            'sanctuary' => $data['sanctuary'],
        ];
    }
}
