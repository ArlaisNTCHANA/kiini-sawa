<?php

namespace App\Personnalite\Psychometrie;

/**
 * Calcul des scores à partir des réponses (1 à 5).
 *
 * Score d'une facette = moyenne des items (items inversés : 6 - réponse), ramenée sur 0-100
 * (« POMP » : pourcentage du maximum possible). Domaine = moyenne de ses facettes.
 * Les percentiles sont ajoutés par NormService quand assez de personnes ont répondu.
 */
final class Scorer
{
    /** @param array<string, int> $answers clé d'item => réponse 1-5 */
    public function ipip(array $answers): array
    {
        $facets = [];
        foreach (IpipItemBank::FACETS as $facet => $label) {
            $values = [];
            foreach (IpipItemBank::itemsOfFacet($facet) as $id) {
                if (isset($answers['ipip:'.$id])) {
                    $v = (int) $answers['ipip:'.$id];
                    $values[] = 1 === IpipItemBank::ITEMS[$id][1] ? $v : 6 - $v;
                }
            }
            if ($values) {
                $facets[$facet] = ['score' => $this->pomp(array_sum($values) / \count($values)), 'items' => \count($values)];
            }
        }
        $domains = [];
        foreach (IpipItemBank::DOMAINS as $d => $label) {
            $scores = array_column(array_filter($facets, fn ($k) => $k[0] === $d, \ARRAY_FILTER_USE_KEY), 'score');
            if ($scores) {
                $domains[$d] = ['score' => round(array_sum($scores) / \count($scores), 1), 'facets' => \count($scores)];
            }
        }

        return ['facets' => $facets, 'domains' => $domains, 'type' => $this->type($facets), 'style' => $this->style($facets)];
    }

    /** Type à 4 lettres et netteté de chaque préférence (0 = indécis, 100 = très net). */
    public function type(array $facets): ?array
    {
        $letters = '';
        $axes = [];
        foreach (Instruments::TYPE_FACETS as $axis => $list) {
            $scores = array_filter(array_map(fn ($f) => $facets[$f]['score'] ?? null, $list), fn ($s) => null !== $s);
            if (\count($scores) < 2) {
                return null;
            }
            $s = array_sum($scores) / \count($scores);
            $letter = $s >= 50 ? $axis[0] : $axis[1];
            $letters .= $letter;
            $axes[$axis] = ['score' => round($s, 1), 'letter' => $letter, 'clarity' => (int) round(abs($s - 50) * 2)];
        }
        // Lettres dans l'ordre E/I S/N T/F J/P : l'axe SN « élevé = N », TF « élevé = F » sont inversés
        $code = ($axes['EI']['letter']).($axes['SN']['score'] >= 50 ? 'N' : 'S').($axes['TF']['score'] >= 50 ? 'F' : 'T').($axes['JP']['letter']);
        foreach (['SN' => ['N', 'S'], 'TF' => ['F', 'T']] as $axis => [$high, $low]) {
            $axes[$axis]['letter'] = $axes[$axis]['score'] >= 50 ? $high : $low;
        }

        return ['code' => $code, 'axes' => $axes];
    }

    /** Scores des 4 styles (0-100) et style principal / secondaire. */
    public function style(array $facets): ?array
    {
        $scores = [];
        foreach (Instruments::STYLE_FACETS as $style => $list) {
            $values = [];
            foreach ($list as $f) {
                $reverse = str_starts_with($f, '!');
                $f = ltrim($f, '!');
                if (isset($facets[$f])) {
                    $values[] = $reverse ? 100 - $facets[$f]['score'] : $facets[$f]['score'];
                }
            }
            if (\count($values) < 2) {
                return null;
            }
            $scores[$style] = round(array_sum($values) / \count($values), 1);
        }
        arsort($scores);
        $order = array_keys($scores);

        return ['scores' => $scores, 'primary' => $order[0], 'secondary' => $scores[$order[1]] >= 50 && ($scores[$order[0]] - $scores[$order[1]]) < 12 ? $order[1] : null];
    }

    public function enneagram(array $answers): array
    {
        $byType = array_fill_keys(range(1, 9), []);
        foreach (EnneagramBank::ITEMS as $id => [$type]) {
            if (isset($answers['enn:'.$id])) {
                $byType[$type][] = (int) $answers['enn:'.$id];
            }
        }
        $scores = array_map(fn ($v) => $v ? $this->pomp(array_sum($v) / \count($v)) : 0, $byType);
        arsort($scores);
        $main = (int) array_key_first($scores);
        [$w1, $w2] = EnneagramBank::wingsOf($main);
        $order = array_keys($scores);

        return [
            'scores' => $scores,
            'type' => $main,
            'wing' => $scores[$w1] >= $scores[$w2] ? $w1 : $w2,
            'clarity' => (int) round($scores[$order[0]] - $scores[$order[1]]),
        ];
    }

    public function gifts(array $answers): array
    {
        $byGift = array_fill_keys(array_keys(SpiritualGiftsBank::GIFTS), []);
        foreach (SpiritualGiftsBank::ITEMS as $id => [$gift]) {
            if (isset($answers['don:'.$id])) {
                $byGift[$gift][] = (int) $answers['don:'.$id];
            }
        }
        $scores = array_map(fn ($v) => $v ? $this->pomp(array_sum($v) / \count($v)) : 0, $byGift);
        arsort($scores);

        return ['scores' => $scores, 'top' => \array_slice(array_keys($scores), 0, 3)];
    }

    /**
     * Qualité des réponses : réponses toutes identiques, temps trop court, incohérence
     * entre items de sens opposé d'une même facette.
     */
    public function quality(array $answers, ?int $seconds): array
    {
        $values = array_map('intval', $answers);
        $warnings = [];
        if (\count($values) >= 20) {
            $counts = array_count_values($values);
            if (max($counts) / \count($values) > 0.8) {
                $warnings[] = 'Vous avez donné presque toujours la même réponse : le résultat est peu fiable.';
            }
        }
        if (null !== $seconds && $seconds < \count($values) * 1.5) {
            $warnings[] = 'Vous avez répondu très vite : prenez le temps de relire les questions qui vous font hésiter.';
        }
        $gaps = [];
        foreach (array_keys(IpipItemBank::FACETS) as $facet) {
            $pos = $neg = [];
            foreach (IpipItemBank::itemsOfFacet($facet) as $id) {
                if (isset($values['ipip:'.$id])) {
                    1 === IpipItemBank::ITEMS[$id][1] ? $pos[] = $values['ipip:'.$id] : $neg[] = 6 - $values['ipip:'.$id];
                }
            }
            if ($pos && $neg) {
                $gaps[] = abs(array_sum($pos) / \count($pos) - array_sum($neg) / \count($neg));
            }
        }
        if (\count($gaps) >= 5 && array_sum($gaps) / \count($gaps) > 1.8) {
            $warnings[] = 'Certaines réponses se contredisent : le résultat est à interpréter avec prudence.';
        }

        return ['reliable' => [] === $warnings, 'warnings' => $warnings];
    }

    private function pomp(float $mean): float
    {
        return round(($mean - 1) / 4 * 100, 1);
    }
}
