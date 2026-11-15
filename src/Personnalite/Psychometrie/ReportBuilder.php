<?php

namespace App\Personnalite\Psychometrie;

use App\Personnalite\Entity\Assessment;

/**
 * Prépare le rapport d'une passation (écran et PDF) : textes, niveaux, percentiles, forces et vigilances.
 */
final class ReportBuilder
{
    public function __construct(private readonly NormService $norms)
    {
    }

    public function build(Assessment $a): array
    {
        $def = Instruments::CATALOG[$a->getInstrument()];
        $scores = $a->getScores();
        $report = ['instrument' => $def, 'slug' => $a->getInstrument(), 'assessment' => $a, 'quality' => $a->getQuality()];

        return $report + match ($def['bank']) {
            'ipip' => $this->ipip($scores, $a->getInstrument()),
            'enn' => $this->enneagram($scores),
            default => $this->gifts($scores),
        };
    }

    public function ipip(array $scores, string $slug = 'profil-complet'): array
    {
        $domains = [];
        foreach ($scores['domains'] as $d => $v) {
            $percentile = $this->norms->percentile($d, $v['score']);
            $ref = $percentile ?? $v['score'];
            $facets = [];
            foreach (IpipItemBank::facetsOfDomain($d) as $f) {
                if (!isset($scores['facets'][$f])) {
                    continue;
                }
                $fs = $scores['facets'][$f]['score'];
                $fp = $this->norms->percentile($f, $fs);
                $lvl = Interpretations::level($fp ?? $fs);
                $facets[$f] = ['label' => IpipItemBank::FACETS[$f], 'score' => $fs, 'percentile' => $fp, 'level' => $lvl,
                    'text' => 'high' === $lvl ? Interpretations::FACETS[$f][0] : ('low' === $lvl ? Interpretations::FACETS[$f][1] : null)];
            }
            $lvl = Interpretations::level($ref);
            $domains[$d] = ['label' => IpipItemBank::DOMAINS[$d], 'score' => $v['score'], 'percentile' => $percentile, 'level' => $lvl,
                'what' => Interpretations::DOMAINS[$d]['what'], 'text' => Interpretations::DOMAINS[$d][$lvl], 'facets' => $facets];
        }

        // Forces : facettes favorables les plus élevées ; vigilances : facettes sensibles
        $all = [];
        foreach ($domains as $d => $dom) {
            foreach ($dom['facets'] as $f => $fac) {
                $all[$f] = $fac + ['domain' => $d];
            }
        }
        $favourable = array_filter($all, fn ($f) => 'N' !== $f['domain'] && 'high' === $f['level'] && !\in_array($f['label'], ['Goût du frisson'], true));
        uasort($favourable, fn ($x, $y) => $y['score'] <=> $x['score']);
        $vigilance = array_filter($all, fn ($f, $k) => ('N' === $f['domain'] && 'high' === $f['level']) || (\in_array($k, ['C5', 'C6', 'A4', 'C3'], true) && 'low' === $f['level']), \ARRAY_FILTER_USE_BOTH);

        $type = $scores['type'] ? [
            'code' => $scores['type']['code'],
            'name' => Interpretations::TYPES[$scores['type']['code']][0],
            'desc' => Interpretations::TYPES[$scores['type']['code']][1],
            'axes' => array_map(fn ($axis, $k) => $axis + ['text' => Interpretations::TYPE_AXES[$k][$axis['letter']]], $scores['type']['axes'], array_keys($scores['type']['axes'])),
        ] : null;
        $style = $scores['style'] ? $scores['style'] + [
            'profile' => Interpretations::STYLES[$scores['style']['primary']],
            'secondaryProfile' => $scores['style']['secondary'] ? Interpretations::STYLES[$scores['style']['secondary']] : null,
        ] : null;

        return [
            'domains' => \in_array($slug, ['type', 'style'], true) ? [] : $domains,
            'strengths' => \array_slice($favourable, 0, 5, true),
            'vigilance' => \in_array($slug, ['type', 'style'], true) ? [] : \array_slice($vigilance, 0, 4, true),
            'type' => 'style' === $slug ? null : $type,
            'style' => 'type' === $slug ? null : $style,
            'normsAvailable' => null !== $this->norms->percentile('E', 50),
        ];
    }

    private function enneagram(array $scores): array
    {
        $bars = [];
        foreach ($scores['scores'] as $t => $s) {
            $bars[$t] = ['name' => EnneagramBank::TYPES[$t]['name'], 'score' => $s];
        }

        return ['enneagram' => $scores + ['profile' => EnneagramBank::TYPES[$scores['type']], 'wingProfile' => EnneagramBank::TYPES[$scores['wing']], 'bars' => $bars]];
    }

    private function gifts(array $scores): array
    {
        $bars = [];
        foreach ($scores['scores'] as $g => $s) {
            $bars[$g] = SpiritualGiftsBank::GIFTS[$g] + ['score' => $s];
        }

        return ['gifts' => ['top' => array_map(fn ($g) => SpiritualGiftsBank::GIFTS[$g] + ['score' => $scores['scores'][$g]], $scores['top']), 'bars' => $bars]];
    }
}
