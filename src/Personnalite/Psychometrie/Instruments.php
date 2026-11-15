<?php

namespace App\Personnalite\Psychometrie;

/**
 * Catalogue des questionnaires. Les questionnaires « IPIP » puisent tous dans la même banque d'items :
 * les réponses se cumulent (le profil complet alimente le type et le style, et inversement).
 *
 * Clé d'item : « ipip:12 », « enn:3 », « don:5 ».
 */
final class Instruments
{
    /** Facettes utilisées pour le type de fonctionnement (approche de Jung, dérivée du Big Five). */
    public const TYPE_FACETS = [
        'EI' => ['E1', 'E2', 'E6'],          // élevé = E
        'SN' => ['O1', 'O4', 'O5'],          // élevé = N
        'TF' => ['A3', 'A6', 'O3'],          // élevé = F
        'JP' => ['C2', 'C5', 'C6'],          // élevé = J
    ];

    /** Facettes du style comportemental (modèle DISC) ; « ! » = score inversé. */
    public const STYLE_FACETS = [
        'D' => ['E3', 'E4', '!A4', '!A5'],
        'I' => ['E1', 'E2', 'E6', 'E5'],
        'S' => ['A4', 'A1', '!E5', '!N2'],
        'C' => ['C2', 'C6', 'C3'],
    ];

    public const CATALOG = [
        'profil-complet' => [
            'name' => 'Profil complet', 'subtitle' => 'Big Five en 29 facettes, type et style',
            'description' => 'Le questionnaire de référence (IPIP-NEO, domaine public, utilisé en recherche). Il produit votre Big Five détaillé, votre type de fonctionnement et votre style comportemental.',
            'bank' => 'ipip', 'facets' => 'all', 'perFacet' => 4, 'minutes' => 15,
            'icon' => 'layers', 'color' => '#0F766E', 'bg' => '#F0FDFA', 'level' => 'Validé scientifiquement',
        ],
        'big-five' => [
            'name' => 'Big Five essentiel', 'subtitle' => 'Les 5 grandes dimensions',
            'description' => 'Version courte du profil (2 questions par facette) : vos 5 grandes dimensions de personnalité.',
            'bank' => 'ipip', 'facets' => 'all', 'perFacet' => 2, 'minutes' => 8,
            'icon' => 'sparkles', 'color' => '#10B981', 'bg' => '#ECFDF5', 'level' => 'Validé scientifiquement',
        ],
        'type' => [
            'name' => 'Type de fonctionnement', 'subtitle' => '16 types, approche de Carl Jung',
            'description' => 'Comment vous puisez votre énergie, percevez, décidez et vous organisez. Inspiré de la typologie de Jung (popularisée par le MBTI®, sans affiliation).',
            'bank' => 'ipip', 'facets' => ['E1', 'E2', 'E6', 'O1', 'O4', 'O5', 'A3', 'A6', 'O3', 'C2', 'C5', 'C6'], 'perFacet' => 4, 'minutes' => 7,
            'icon' => 'brain', 'color' => '#6366F1', 'bg' => '#EEF2FF', 'level' => 'Questions validées',
        ],
        'style' => [
            'name' => 'Style comportemental', 'subtitle' => '4 styles : Meneur, Rayonnant, Constant, Méthodique',
            'description' => 'Votre manière d\'agir et de communiquer, d\'après le modèle DISC de William Marston (1928). Idéal pour mieux travailler en équipe et en couple.',
            'bank' => 'ipip', 'facets' => ['E3', 'E4', 'A4', 'A5', 'E1', 'E2', 'E6', 'E5', 'A1', 'N2', 'C2', 'C6', 'C3'], 'perFacet' => 4, 'minutes' => 7,
            'icon' => 'zap', 'color' => '#F43F5E', 'bg' => '#FFF1F2', 'level' => 'Questions validées',
        ],
        'enneagramme' => [
            'name' => 'Ennéagramme', 'subtitle' => '9 motivations profondes',
            'description' => 'Ce qui vous pousse et ce que vous craignez au fond : votre type, votre aile et votre chemin de croissance.',
            'bank' => 'enn', 'minutes' => 10,
            'icon' => 'target', 'color' => '#F59E0B', 'bg' => '#FFFBEB', 'level' => 'Outil d\'introspection',
        ],
        'dons-spirituels' => [
            'name' => 'Dons spirituels', 'subtitle' => '15 dons bibliques',
            'description' => 'Discernez les dons que Dieu vous a confiés pour servir (Romains 12, 1 Corinthiens 12, Éphésiens 4, 1 Pierre 4).',
            'bank' => 'don', 'minutes' => 8,
            'icon' => 'flame', 'color' => '#B45309', 'bg' => '#FFF7ED', 'level' => 'Outil de discernement',
        ],
    ];

    public static function exists(string $slug): bool
    {
        return isset(self::CATALOG[$slug]);
    }

    /**
     * Questions d'un questionnaire, dans un ordre mélangé stable (les items d'une même facette ne se suivent pas).
     *
     * @return list<array{key: string, text: string}>
     */
    public static function items(string $slug): array
    {
        $def = self::CATALOG[$slug];
        $items = [];
        if ('ipip' === $def['bank']) {
            $facets = 'all' === $def['facets'] ? array_keys(IpipItemBank::FACETS) : $def['facets'];
            foreach ($facets as $facet) {
                foreach (\array_slice(IpipItemBank::itemsOfFacet($facet), 0, $def['perFacet']) as $id) {
                    $items[] = ['key' => 'ipip:'.$id, 'text' => IpipItemBank::ITEMS[$id][2]];
                }
            }
        } elseif ('enn' === $def['bank']) {
            foreach (EnneagramBank::ITEMS as $id => [, $text]) {
                $items[] = ['key' => 'enn:'.$id, 'text' => $text];
            }
        } else {
            foreach (SpiritualGiftsBank::ITEMS as $id => [, $text]) {
                $items[] = ['key' => 'don:'.$id, 'text' => $text];
            }
        }
        // Mélange déterministe : même ordre pour tous, facettes entrelacées
        usort($items, fn ($a, $b) => crc32($a['key'].'ks') <=> crc32($b['key'].'ks'));

        return $items;
    }
}
