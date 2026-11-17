<?php

namespace App\Personnalite\Psychometrie;

use App\Personnalite\Entity\Assessment;

/**
 * Prépare le rapport d'une passation (écran et PDF) : textes, niveaux, percentiles, forces et vigilances.
 */
final class ReportBuilder
{
    /** Rapports illustrés (page de garde, schémas, conclusion) ; les autres utilisent _report_body. */
    public const TEMPLATES = [
        'disc' => 'personnalite/assessment/disc/_report.html.twig',
        'enneagramme' => 'personnalite/assessment/report/enneagram.html.twig',
        'dons-spirituels' => 'personnalite/assessment/report/gifts.html.twig',
        'profil-complet' => 'personnalite/assessment/report/bigfive.html.twig',
        'big-five' => 'personnalite/assessment/report/bigfive.html.twig',
        'type' => 'personnalite/assessment/report/type.html.twig',
    ];

    public function __construct(private readonly NormService $norms)
    {
    }

    public function build(Assessment $a): array
    {
        $def = Instruments::CATALOG[$a->getInstrument()];
        $scores = $a->getScores();
        $report = ['instrument' => $def, 'slug' => $a->getInstrument(), 'assessment' => $a, 'quality' => $a->getQuality(),
            'template' => self::TEMPLATES[$a->getInstrument()] ?? null];

        return $report + match ($def['bank']) {
            'ipip' => $this->ipip($scores, $a->getInstrument()),
            'enn' => $this->enneagram($scores),
            'disc' => $this->disc($scores),
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
            'bf' => \in_array($slug, ['profil-complet', 'big-five'], true) ? $this->bigFiveExtras($domains) : null,
            'typeX' => $type && 'style' !== $slug ? $this->typeExtras($type) : null,
            'domains' => \in_array($slug, ['type', 'style'], true) ? [] : $domains,
            'strengths' => \array_slice($favourable, 0, 5, true),
            'vigilance' => \in_array($slug, ['type', 'style'], true) ? [] : \array_slice($vigilance, 0, 4, true),
            'type' => 'style' === $slug ? null : $type,
            // Le style déduit du Big Five ne s'affiche plus que dans les anciens rapports « style » : le test DISC le remplace
            'style' => 'style' === $slug ? $style : null,
            'normsAvailable' => null !== $this->norms->percentile('E', 50),
        ];
    }

    public function disc(array $scores): array
    {
        $p = DiscProfiles::PROFILES;
        $primary = $scores['primary'];
        $secondary = $scores['secondary'];
        $code = DiscCombos::code($primary, $secondary);
        $combo = DiscCombos::PROFILES[$code] + ['code' => $code];
        $others = [];
        foreach (DiscBank::LETTERS as $l) {
            $others[$l] = ['profile' => $p[$l], 'perception' => DiscProfiles::PERCEPTION[$primary][$l], 'interaction' => DiscProfiles::INTERACTION[$primary][$l]];
        }
        // Ce que le contexte demande en plus ou en moins (écart d'au moins 8 points)
        $more = $less = [];
        foreach ($scores['shifts'] as $l => $shift) {
            if ($shift >= 8) {
                $more[] = $p[$l]['more'];
            } elseif ($shift <= -8) {
                $less[] = $p[$l]['name'];
            }
        }

        return ['disc' => $scores + [
            'profile' => $p[$primary],
            'secondaryProfile' => $secondary ? $p[$secondary] : null,
            'adaptedProfile' => $p[$scores['adaptedPrimary']],
            'combo' => $combo,
            'verse' => DiscCombos::VERSES[$primary],
            'meditation' => DiscCombos::MEDITATIONS[$primary],
            'familyFigures' => DiscCombos::familyFigures($primary, $code),
            'adaptedCode' => $scores['adaptedPrimary'],
            'charts' => DiscCharts::all($scores),
            'gapReading' => DiscProfiles::gapReading($scores['gap']),
            'more' => $more, 'less' => $less,
            'profiles' => $p,
            'others' => $others,
        ]];
    }

    /** Rapport Big Five illustré : profil par dimension, radar, jauges, facettes. */
    private function bigFiveExtras(array $domains): array
    {
        $out = [];
        $axes = [];
        foreach (['E', 'O', 'A', 'C', 'N'] as $d) {
            if (!isset($domains[$d])) {
                continue;
            }
            $dom = $domains[$d];
            $x = BigFiveProfiles::DOMAINS[$d];
            $value = $dom['percentile'] ?? $dom['score'];
            $axes[$d] = ['label' => $dom['label'], 'value' => $value];
            $rows = array_map(fn ($f) => ['label' => $f['label'], 'value' => $f['percentile'] ?? $f['score'], 'color' => $x['color'], 'strong' => 'mid' !== $f['level']], $dom['facets']);
            $out[$d] = $dom + ['x' => $x, 'level' => $dom['level'], 'lx' => $x[$dom['level']], 'value' => $value,
                'gauge' => ReportCharts::uri(ReportCharts::levelGauge($value, $x['color'])),
                'facetChart' => $rows ? ReportCharts::uri(ReportCharts::hbars(array_values($rows), 200)) : null];
        }
        // Les trois dimensions les plus marquées (loin de la moyenne) résument le profil
        $marked = $out;
        uasort($marked, fn ($a, $b) => abs($b['value'] - 50) <=> abs($a['value'] - 50));
        $marked = \array_slice($marked, 0, 3, true);

        return ['domains' => $out, 'marked' => $marked, 'headline' => implode(' · ', array_map(fn ($m) => $m['lx']['label'], $marked)),
            'radar' => ReportCharts::uri(ReportCharts::radar($axes, '#0F766E'))];
    }

    /** Rapport « type de fonctionnement » illustré. */
    private function typeExtras(array $type): array
    {
        $code = $type['code'];
        $family = TypeProfiles::FAMILIES[TypeProfiles::familyOf($code)];
        $rows = [];
        $keys = array_keys(Instruments::TYPE_FACETS);
        foreach (array_values($type['axes']) as $i => $axis) {
            $key = $keys[$i];
            $rows[] = ['left' => $key[0], 'right' => $key[1], 'leftName' => TypeProfiles::LETTERS[$key[0]], 'rightName' => TypeProfiles::LETTERS[$key[1]],
                'letter' => $axis['letter'], 'clarity' => $axis['clarity']];
        }
        $names = array_map(fn ($t) => $t[0], Interpretations::TYPES);

        return TypeProfiles::TYPES[$code] + ['family' => $family, 'familyKey' => TypeProfiles::familyOf($code), 'families' => TypeProfiles::FAMILIES,
            'axesChart' => ReportCharts::uri(ReportCharts::typeAxes($rows, $family['color'])),
            'grid' => ReportCharts::uri(ReportCharts::typeGrid($code, $family['color'], $names))];
    }

    private function enneagram(array $scores): array
    {
        $bars = [];
        foreach ($scores['scores'] as $t => $s) {
            $bars[$t] = ['name' => EnneagramBank::TYPES[$t]['name'], 'score' => $s];
        }

        $t = $scores['type'];
        $x = EnneagramProfiles::TYPES[$t];
        [$grow, $stress] = EnneagramProfiles::ARROWS[$t];
        $rows = [];
        foreach ($scores['scores'] as $k => $v) {
            $rows[] = ['label' => $k.' · '.EnneagramBank::TYPES[$k]['name'], 'value' => $v, 'color' => EnneagramProfiles::CENTERS[EnneagramProfiles::centerOf($k)]['color'], 'strong' => $k === $t];
        }

        return ['enneagram' => $scores + [
            'profile' => EnneagramBank::TYPES[$t], 'wingProfile' => EnneagramBank::TYPES[$scores['wing']], 'bars' => $bars,
            'x' => $x, 'center' => EnneagramProfiles::CENTERS[$x['center']], 'centerKey' => $x['center'],
            'wingName' => EnneagramProfiles::WINGS[$t][$scores['wing']],
            'grow' => ['n' => $grow, 'name' => EnneagramBank::TYPES[$grow]['name']], 'stress' => ['n' => $stress, 'name' => EnneagramBank::TYPES[$stress]['name']],
            'centers' => EnneagramProfiles::CENTERS,
            'charts' => ['circle' => ReportCharts::uri(ReportCharts::enneagram($t, $scores['wing'])), 'bars' => ReportCharts::uri(ReportCharts::hbars($rows))],
        ]];
    }

    private function gifts(array $scores): array
    {
        $bars = [];
        foreach ($scores['scores'] as $g => $s) {
            $bars[$g] = SpiritualGiftsBank::GIFTS[$g] + ['score' => $s];
        }

        $rows = [];
        foreach ($scores['scores'] as $g => $v) {
            $rows[] = ['label' => SpiritualGiftsBank::GIFTS[$g]['name'], 'value' => $v, 'color' => GiftProfiles::FAMILIES[GiftProfiles::familyOf($g)]['color'], 'strong' => \in_array($g, $scores['top'], true)];
        }
        $families = [];
        foreach (GiftProfiles::FAMILIES as $key => $f) {
            $vals = array_map(fn ($g) => $scores['scores'][$g] ?? 0, $f['gifts']);
            $families[$key] = $f + ['avg' => (int) round(array_sum($vals) / \count($vals))];
        }
        $top = array_map(fn ($g) => SpiritualGiftsBank::GIFTS[$g] + GiftProfiles::GIFTS[$g] + ['score' => $scores['scores'][$g], 'code' => $g,
            'family' => GiftProfiles::FAMILIES[GiftProfiles::familyOf($g)]], $scores['top']);
        uasort($families, fn ($a, $b) => $b['avg'] <=> $a['avg']);

        return ['gifts' => ['top' => $top, 'bars' => $bars, 'families' => $families, 'mainFamily' => array_key_first($families),
            'charts' => ['bars' => ReportCharts::uri(ReportCharts::hbars($rows, 150))]]];
    }
}
