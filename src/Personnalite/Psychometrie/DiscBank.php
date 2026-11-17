<?php

namespace App\Personnalite\Psychometrie;

/**
 * Questionnaire DISC KIINI SAWA (modèle de W. M. Marston, 1928) : 24 blocs de 4 mots, un par dimension.
 * Pour chaque bloc, on choisit le mot qui nous ressemble le PLUS et celui qui nous ressemble le MOINS.
 *
 * - « Le plus » → profil adapté (ce que l'on montre en contexte) ;
 * - « Le moins », inversé → profil naturel (spontané, qui ressort sous pression) ;
 * - l'écart entre les deux → l'effort d'adaptation.
 *
 * Mots rédigés pour KIINI SAWA (aucun questionnaire commercial n'est repris). Clé de réponse : « disc:{bloc} ».
 */
final class DiscBank
{
    public const LETTERS = ['D', 'I', 'S', 'C'];

    /** Bloc => [D, I, S, C] */
    public const BLOCKS = [
        1 => ['Décidé', 'Enthousiaste', 'Patient', 'Précis'],
        2 => ['Direct', 'Sociable', 'Fidèle', 'Méthodique'],
        3 => ['Audacieux', 'Expressif', 'Calme', 'Prudent'],
        4 => ['Exigeant', 'Optimiste', 'Conciliant', 'Rigoureux'],
        5 => ['Compétitif', 'Spontané', 'Loyal', 'Analytique'],
        6 => ['Volontaire', 'Chaleureux', 'Serviable', 'Organisé'],
        7 => ['Combatif', 'Persuasif', 'Accommodant', 'Perfectionniste'],
        8 => ['Affirmé', 'Joyeux', 'Posé', 'Consciencieux'],
        9 => ['Ambitieux', 'Communicatif', 'Constant', 'Logique'],
        10 => ['Indépendant', 'Inspirant', 'À l\'écoute', 'Factuel'],
        11 => ['Pressé d\'agir', 'Charmeur', 'Bienveillant', 'Minutieux'],
        12 => ['Entreprenant', 'Convaincant', 'Tolérant', 'Exact'],
        13 => ['Tenace', 'Animé', 'Paisible', 'Réfléchi'],
        14 => ['Franc', 'Démonstratif', 'Coopératif', 'Discipliné'],
        15 => ['Autoritaire', 'Populaire', 'Modeste', 'Sceptique'],
        16 => ['Rapide à trancher', 'Créatif', 'Régulier', 'Prévoyant'],
        17 => ['Conquérant', 'Divertissant', 'Doux', 'Vigilant'],
        18 => ['Résolu', 'Ouvert aux autres', 'Stable', 'Structuré'],
        19 => ['Intrépide', 'Ludique', 'Dévoué', 'Objectif'],
        20 => ['Fonceur', 'Rayonnant', 'Arrangeant', 'Soigneux'],
        21 => ['Énergique', 'Bavard', 'Prévisible', 'Cartésien'],
        22 => ['Meneur', 'Fédérateur', 'Solidaire', 'Expert'],
        23 => ['Impatient', 'Impulsif', 'Hésitant', 'Critique'],
        24 => ['Dominant', 'Influent', 'Rassurant', 'Respectueux des règles'],
    ];

    /**
     * Mots d'un bloc dans un ordre qui varie d'un bloc à l'autre (pour ne pas toujours voir D en premier).
     *
     * @return list<array{letter: string, word: string}>
     */
    public static function shuffled(int $block): array
    {
        $words = array_map(fn ($l, $w) => ['letter' => $l, 'word' => $w], self::LETTERS, self::BLOCKS[$block]);
        $offset = ($block * 3) % 4;

        return array_merge(\array_slice($words, $offset), \array_slice($words, 0, $offset));
    }

    /**
     * Scores : « adapté » (le plus) et « naturel » (le moins, inversé), en parts de 100.
     *
     * @param array<string, string> $answers « disc:{bloc} » => deux lettres : le plus puis le moins (ex. « DS »)
     *
     * @return array{adapted: array<string, int>, natural: array<string, int>, most: array<string, int>, least: array<string, int>, primary: string, secondary: ?string, adaptedPrimary: string, gap: int, shifts: array<string, int>}
     */
    public static function score(array $answers): array
    {
        $most = $least = array_fill_keys(self::LETTERS, 0);
        foreach ($answers as $pair) {
            ++$most[$pair[0]];
            ++$least[$pair[1]];
        }
        $n = \count($answers) ?: 1;
        $adapted = self::shares($most);
        $naturalCounts = array_map(fn ($l) => $n - $l, $least);
        $natural = self::shares($naturalCounts);
        $shifts = [];
        $gap = 0;
        foreach (self::LETTERS as $l) {
            $shifts[$l] = $adapted[$l] - $natural[$l];
            $gap += abs($shifts[$l]);
        }
        // Ordre du profil naturel ; à égalité, la dimension la plus souvent choisie en « le plus » l'emporte
        $keys = self::LETTERS;
        usort($keys, fn ($x, $y) => [$naturalCounts[$y], $most[$y]] <=> [$naturalCounts[$x], $most[$x]]);
        $order = array_combine($keys, array_map(fn ($l) => $natural[$l], $keys));
        $adaptedOrder = $adapted;
        arsort($adaptedOrder);

        return [
            'adapted' => $adapted, 'natural' => $natural, 'most' => $most, 'least' => $least,
            'primary' => $keys[0],
            // Deuxième dimension retenue si elle est nettement présente
            'secondary' => $order[$keys[1]] >= 25 ? $keys[1] : null,
            'adaptedPrimary' => array_key_first($adaptedOrder),
            'gap' => (int) round($gap / 2),
            'shifts' => $shifts,
        ];
    }

    /** @param array<string, int> $counts @return array<string, int> parts entières dont la somme fait 100 */
    private static function shares(array $counts): array
    {
        $total = array_sum($counts) ?: 1;
        $shares = array_map(fn ($c) => (int) round(100 * $c / $total), $counts);
        // Ajustement d'arrondi sur la plus grande part
        $shares[array_search(max($shares), $shares, true)] += 100 - array_sum($shares);

        return $shares;
    }
}
