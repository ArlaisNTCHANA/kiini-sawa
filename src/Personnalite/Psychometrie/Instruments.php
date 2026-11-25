<?php

namespace App\Personnalite\Psychometrie;

/**
 * Catalogue des questionnaires. Les questionnaires « IPIP » puisent tous dans la même banque d'items :
 * les réponses se cumulent (le profil complet alimente le type et le style, et inversement).
 *
 * Clé d'item : « ipip:12 », « enn:3 », « don:5 », « disc:7 » (bloc de 4 mots à choix forcé).
 * L'ancien questionnaire « style » (DISC déduit du Big Five) reste lisible pour les rapports existants mais
 * n'apparaît plus au catalogue : le test « disc » le remplace.
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

    /**
     * Niveaux de fiabilité : une seule mention par test, expliquée dans la légende du catalogue.
     */
    public const LEVELS = [
        'Questionnaire scientifique' => 'Questions et échelles validées par la recherche ; leur fiabilité est mesurée.',
        'Issu d\'un questionnaire scientifique' => 'Questions validées ; le classement en types ou en styles en est une lecture simplifiée.',
        'Outil d\'introspection' => 'Pour réfléchir à vos motivations ; à confirmer par l\'expérience et le regard de vos proches.',
        'Outil de connaissance de soi' => 'Issu du modèle de William Marston : il décrit des comportements, pas une valeur ; il éclaire, il n\'enferme pas.',
        'Outil de discernement' => 'À confirmer dans le service, la prière et le regard de votre communauté.',
        'Auto-évaluation' => 'Votre propre regard sur votre situation aujourd\'hui ; à refaire régulièrement pour mesurer le chemin.',
    ];

    /** Regroupement du catalogue. */
    public const GROUPS = [
        'Personnalité' => ['disc', 'profil-complet', 'big-five', 'type'],
        'Motivations et dons' => ['enneagramme', 'dons-spirituels'],
    ];

    public const CATALOG = [
        'disc' => [
            'name' => 'DISC', 'subtitle' => 'Profil naturel et profil adapté',
            'description' => 'Votre manière d\'agir et de communiquer selon 4 dimensions (Dominance, Influence, Stabilité, Conformité), d\'après le modèle de William Marston. Vous découvrez votre profil naturel, votre profil adapté, comment les autres vous perçoivent et comment interagir avec chaque profil.',
            'bank' => 'disc', 'minutes' => 10,
            'icon' => 'zap', 'color' => '#2E6B53', 'bg' => '#EEF3EF', 'level' => 'Outil de connaissance de soi',
        ],
        'profil-complet' => [
            'name' => 'Profil complet', 'subtitle' => 'Big Five en 29 facettes et type',
            'description' => 'Le questionnaire de référence (IPIP-NEO, domaine public, utilisé en recherche). Il produit votre Big Five détaillé et votre type de fonctionnement.',
            'bank' => 'ipip', 'facets' => 'all', 'perFacet' => 4, 'minutes' => 15,
            'icon' => 'layers', 'color' => '#1F4D3A', 'bg' => '#E4ECE7', 'level' => 'Questionnaire scientifique',
        ],
        'big-five' => [
            'name' => 'Big Five essentiel', 'subtitle' => 'Les 5 grandes dimensions',
            'description' => 'Version courte du profil (2 questions par facette) : vos 5 grandes dimensions de personnalité.',
            'bank' => 'ipip', 'facets' => 'all', 'perFacet' => 2, 'minutes' => 8,
            'icon' => 'sparkles', 'color' => '#4F7355', 'bg' => '#EEF4EF', 'level' => 'Questionnaire scientifique',
        ],
        'type' => [
            'name' => 'Type de fonctionnement', 'subtitle' => '16 types, approche de Carl Jung',
            'description' => 'Comment vous puisez votre énergie, percevez, décidez et vous organisez. Inspiré de la typologie de Jung (popularisée par le MBTI®, sans affiliation).',
            'bank' => 'ipip', 'facets' => ['E1', 'E2', 'E6', 'O1', 'O4', 'O5', 'A3', 'A6', 'O3', 'C2', 'C5', 'C6'], 'perFacet' => 4, 'minutes' => 7,
            'icon' => 'brain', 'color' => '#5D7368', 'bg' => '#ECEFED', 'level' => 'Issu d\'un questionnaire scientifique',
        ],
        'style' => [
            'name' => 'Style comportemental', 'subtitle' => '4 styles : Meneur, Rayonnant, Constant, Méthodique',
            'description' => 'Votre manière d\'agir et de communiquer, d\'après le modèle DISC de William Marston (1928). Idéal pour mieux travailler en équipe et en couple.',
            'bank' => 'ipip', 'facets' => ['E3', 'E4', 'A4', 'A5', 'E1', 'E2', 'E6', 'E5', 'A1', 'N2', 'C2', 'C6', 'C3'], 'perFacet' => 4, 'minutes' => 7,
            'icon' => 'zap', 'color' => '#2E6B53', 'bg' => '#EEF3EF', 'level' => 'Issu d\'un questionnaire scientifique',
        ],
        'enneagramme' => [
            'name' => 'Ennéagramme', 'subtitle' => '9 motivations profondes',
            'description' => 'Ce qui vous pousse et ce que vous craignez au fond : votre type, votre aile et votre chemin de croissance.',
            'bank' => 'enn', 'minutes' => 10,
            'icon' => 'target', 'color' => '#8A6418', 'bg' => '#FAF4E6', 'level' => 'Outil d\'introspection',
        ],
        'dons-spirituels' => [
            'name' => 'Dons spirituels', 'subtitle' => '15 dons bibliques',
            'description' => 'Discernez les dons que Dieu vous a confiés pour servir (Romains 12, 1 Corinthiens 12, Éphésiens 4, 1 Pierre 4).',
            'bank' => 'don', 'minutes' => 8,
            'icon' => 'flame', 'color' => '#9A6B12', 'bg' => '#FAF4E6', 'level' => 'Outil de discernement',
        ],
    ];

    public static function exists(string $slug): bool
    {
        return isset(self::CATALOG[$slug]);
    }

    /**
     * Questions d'un questionnaire, dans un ordre stable où les items d'une même facette ne se suivent pas.
     *
     * @return list<array{key: string, text: string}>
     */
    public static function items(string $slug): array
    {
        $def = self::CATALOG[$slug];
        $items = [];
        if ('ipip' === $def['bank']) {
            // Tourniquet : un item de chaque facette, puis le suivant… (jamais deux items d'une même facette à la suite)
            $facets = 'all' === $def['facets'] ? array_keys(IpipItemBank::FACETS) : $def['facets'];
            // Domaines entrelacés aussi : N1, E1, O1, A1, C1, N2, E2…
            $order = array_flip(array_keys(IpipItemBank::DOMAINS));
            usort($facets, fn ($x, $y) => [$x[1], $order[$x[0]]] <=> [$y[1], $order[$y[0]]]);
            $byFacet = array_map(fn ($f) => \array_slice(IpipItemBank::itemsOfFacet($f), 0, $def['perFacet']), $facets);
            for ($round = 0; $round < $def['perFacet']; ++$round) {
                foreach ($byFacet as $list) {
                    if (isset($list[$round])) {
                        $items[] = ['key' => 'ipip:'.$list[$round], 'text' => IpipItemBank::ITEMS[$list[$round]][2]];
                    }
                }
            }
        } elseif ('disc' === $def['bank']) {
            foreach (array_keys(DiscBank::BLOCKS) as $block) {
                $items[] = ['key' => 'disc:'.$block, 'text' => 'Bloc '.$block, 'words' => DiscBank::shuffled($block)];
            }
        } elseif ('enn' === $def['bank']) {
            // Les items sont déjà entrelacés par type (1, 2, … 9, 1, 2…)
            foreach (Content::get('enn.items') as $id => [, $text]) {
                $items[] = ['key' => 'enn:'.$id, 'text' => $text];
            }
        } else {
            foreach (Content::get('gifts.items') as $id => [, $text]) {
                $items[] = ['key' => 'don:'.$id, 'text' => $text];
            }
        }

        return $items;
    }
}
