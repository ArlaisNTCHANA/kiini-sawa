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
                    'text' => 'high' === $lvl ? Content::get('bigfive.facets')[$f][0] : ('low' === $lvl ? Content::get('bigfive.facets')[$f][1] : null)];
            }
            $lvl = Interpretations::level($ref);
            $domains[$d] = ['label' => IpipItemBank::DOMAINS[$d], 'score' => $v['score'], 'percentile' => $percentile, 'level' => $lvl,
                'what' => Content::get('bigfive.domains')[$d]['what'], 'text' => Content::get('bigfive.domains')[$d][$lvl], 'facets' => $facets];
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
            'name' => Content::get('type.types')[$scores['type']['code']][0],
            'desc' => Content::get('type.types')[$scores['type']['code']][1],
            'axes' => array_map(fn ($axis, $k) => $axis + ['text' => Content::get('type.axes')[$k][$axis['letter']]], $scores['type']['axes'], array_keys($scores['type']['axes'])),
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

    /**
     * Regard croisé : le style déduit du Big Five (profil complet ou essentiel) confronté au DISC. Aucun score n'est mélangé :
     * les deux outils mesurent par des méthodes différentes, on dit seulement s'ils se rejoignent.
     *
     * @return array{status: string, text?: string, styleName?: string, source?: string, date?: \DateTimeInterface}
     */
    public function crossView(array $discReport, ?Assessment $ipip): array
    {
        $style = $ipip?->getScores()['style'] ?? null;
        if (!$style) {
            return ['status' => 'missing'];
        }
        $disc = $discReport['disc'];
        $styleName = Interpretations::STYLES[$style['primary']]['name'];
        $discName = $disc['profile']['name'];
        $secondary = $style['secondary'] ?? null;
        if ($disc['primary'] === $style['primary']) {
            $status = 'confirmed';
            $text = 'Deux outils différents, un même constat : votre Big Five fait lui aussi ressortir un style « '.$styleName.' », comme votre DISC (« '.$discName.' »). Votre profil naturel est solide.';
        } elseif ($disc['primary'] === $secondary || ($disc['secondary'] ?? null) === $style['primary']) {
            $status = 'close';
            $text = 'Votre Big Five rejoint en partie votre DISC : il fait ressortir « '.$styleName.' », un style voisin de votre profil « '.$discName.' ». Vos deux résultats se complètent plutôt qu\'ils ne se contredisent.';
        } else {
            $status = 'different';
            $text = 'Votre Big Five fait ressortir « '.$styleName.' », alors que votre DISC place « '.$discName.' » en tête. Ce n\'est pas une contradiction : le DISC mesure ce que vous choisissez de dire de vous (naturel et adapté), le Big Five mesure vos traits à partir d\'affirmations. Notez ce qui diffère : c\'est souvent là que vous vous adaptez, ou que vous vous voyez autrement que vous ne vous comportez.';
        }

        return ['status' => $status, 'text' => $text, 'styleName' => $styleName, 'source' => Instruments::CATALOG[$ipip->getInstrument()]['name'], 'date' => $ipip->getTakenAt()];
    }

    public function disc(array $scores): array
    {
        $p = Content::get('disc.dimensions');
        $primary = $scores['primary'];
        $secondary = $scores['secondary'];
        $code = DiscCombos::code($primary, $secondary);
        $combo = Content::get('disc.profiles')[$code] + ['code' => $code, 'community' => Content::get('disc.community')[$code] ?? null];
        $others = [];
        foreach (DiscBank::LETTERS as $l) {
            $others[$l] = ['profile' => $p[$l], 'perception' => Content::get('disc.perception')[$primary][$l], 'interaction' => Content::get('disc.interaction')[$primary][$l]];
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
            'intensity' => self::discIntensity($scores['natural'][$primary], $p[$primary]['name'], $secondary ? $p[$secondary]['name'] : null),
            'adaptation' => $scores['adaptedPrimary'] !== $primary ? (Content::get('disc.adaptation')[$primary][$scores['adaptedPrimary']] ?? null) : null,
            'figureSheets' => array_map(fn ($f) => BiblicalFigures::find($f['name']), $combo['figures']),
            'verse' => Content::get('disc.verses')[$primary],
            'meditation' => Content::get('disc.meditations')[$primary],
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
            $x = Content::get('bigfive.levels')[$d];
            $value = $dom['percentile'] ?? $dom['score'];
            $axes[$d] = ['label' => $dom['label'], 'value' => $value];
            $rows = array_map(fn ($f) => ['label' => $f['label'], 'value' => $f['percentile'] ?? $f['score'], 'color' => $x['color'], 'strong' => 'mid' !== $f['level']], $dom['facets']);
            $out[$d] = $dom + ['x' => $x, 'level' => $dom['level'], 'lx' => $x[$dom['level']], 'value' => $value,
                'extreme' => $value >= 80 ? 'Ce trait est très marqué chez vous : ses forces comme ses points de vigilance seront nettement visibles dans votre vie.' : ($value <= 20 ? 'Ce trait est très peu présent chez vous : c\'est une vraie singularité, qui a ses forces et ses points de vigilance.' : null),
                'gauge' => ReportCharts::uri(ReportCharts::levelGauge($value, $x['color'])),
                'facetChart' => $rows ? ReportCharts::uri(ReportCharts::hbars(array_values($rows), 200)) : null];
        }
        // Les trois dimensions les plus marquées (loin de la moyenne) résument le profil
        $marked = $out;
        uasort($marked, fn ($a, $b) => abs($b['value'] - 50) <=> abs($a['value'] - 50));
        $marked = \array_slice($marked, 0, 3, true);

        return ['domains' => $out, 'marked' => $marked, 'headline' => implode(' · ', array_map(fn ($m) => $m['lx']['label'], $marked)),
            'radar' => ReportCharts::uri(ReportCharts::radar($axes, '#2E6B53'))];
    }

    /** Rapport « type de fonctionnement » illustré. */
    private function typeExtras(array $type): array
    {
        $code = $type['code'];
        $family = Content::get('type.families')[TypeProfiles::familyOf($code)];
        $rows = [];
        $keys = array_keys(Instruments::TYPE_FACETS);
        foreach (array_values($type['axes']) as $i => $axis) {
            $key = $keys[$i];
            $rows[] = ['left' => $key[0], 'right' => $key[1], 'leftName' => TypeProfiles::LETTERS[$key[0]], 'rightName' => TypeProfiles::LETTERS[$key[1]],
                'letter' => $axis['letter'], 'clarity' => $axis['clarity']];
        }
        $names = array_map(fn ($t) => $t[0], Content::get('type.types'));

        return Content::get('type.profiles')[$code] + ['community' => Content::get('type.community')[$code] ?? null, 'family' => $family, 'familyKey' => TypeProfiles::familyOf($code), 'families' => Content::get('type.families'),
            'axesChart' => ReportCharts::uri(ReportCharts::typeAxes($rows, $family['color'])),
            'grid' => ReportCharts::uri(ReportCharts::typeGrid($code, $family['color'], $names))];
    }

    /** Netteté de la dimension DISC dominante (part du profil naturel). */
    public static function discIntensity(int $share, string $name, ?string $secondary): array
    {
        return match (true) {
            $share >= 45 => ['Très marquée', sprintf('Votre dimension %s est très marquée (%d %%) : ce portrait devrait vous ressembler fortement, avec ses forces comme ses angles morts.', mb_strtolower($name), $share)],
            $share >= 35 => ['Marquée', sprintf('Votre dimension %s est nettement présente (%d %%)%s.', mb_strtolower($name), $share, $secondary ? ', colorée par votre côté '.mb_strtolower($secondary) : '')],
            default => ['Modérée', sprintf('Votre dimension %s ressort, mais de peu (%d %%) : vos quatre dimensions sont assez équilibrées. Lisez aussi les autres profils : certains traits vous ressembleront autant.', mb_strtolower($name), $share)],
        };
    }

    /** Niveau d'un don (score sur 100). */
    public static function giftStrength(float $score): array
    {
        return match (true) {
            $score >= 80 => ['Très présent', 'Ce don est très présent dans vos réponses.'],
            $score >= 60 => ['Présent', 'Ce don est bien présent dans vos réponses.'],
            default => ['En germe', 'Ce don est encore discret : il demande à être exercé pour se révéler.'],
        };
    }

    private function enneagram(array $scores): array
    {
        $bars = [];
        foreach ($scores['scores'] as $t => $s) {
            $bars[$t] = ['name' => Content::get('enn.types')[$t]['name'], 'score' => $s];
        }

        $t = $scores['type'];
        $x = Content::get('enn.profiles')[$t] + ['community' => Content::get('enn.community')[$t] ?? null];
        [$grow, $stress] = EnneagramProfiles::ARROWS[$t];
        $rows = [];
        foreach ($scores['scores'] as $k => $v) {
            $rows[] = ['label' => $k.' · '.Content::get('enn.types')[$k]['name'], 'value' => $v, 'color' => Content::get('enn.centers')[EnneagramProfiles::centerOf($k)]['color'], 'strong' => $k === $t];
        }

        return ['enneagram' => $scores + [
            'profile' => Content::get('enn.types')[$t], 'wingProfile' => Content::get('enn.types')[$scores['wing']], 'bars' => $bars,
            'clarityLabel' => $scores['clarity'] >= 15 ? ['Type net', 'Votre type ressort nettement des autres : ce portrait devrait vous parler fortement.'] : ($scores['clarity'] >= 8 ? ['Type assez net', 'Votre type ressort, mais un ou deux autres types sont proches : lisez aussi leurs portraits.'] : null),
            'x' => $x, 'center' => Content::get('enn.centers')[$x['center']], 'centerKey' => $x['center'],
            'wingName' => Content::get('enn.wings')[$t][$scores['wing']],
            'grow' => ['n' => $grow, 'name' => Content::get('enn.types')[$grow]['name']], 'stress' => ['n' => $stress, 'name' => Content::get('enn.types')[$stress]['name']],
            'centers' => Content::get('enn.centers'),
            'charts' => ['circle' => ReportCharts::uri(ReportCharts::enneagram($t, $scores['wing'])), 'bars' => ReportCharts::uri(ReportCharts::hbars($rows))],
        ]];
    }

    private function gifts(array $scores): array
    {
        $bars = [];
        foreach ($scores['scores'] as $g => $s) {
            $bars[$g] = Content::get('gifts.gifts')[$g] + ['score' => $s];
        }

        $rows = [];
        foreach ($scores['scores'] as $g => $v) {
            $rows[] = ['label' => Content::get('gifts.gifts')[$g]['name'], 'value' => $v, 'color' => Content::get('gifts.families')[GiftProfiles::familyOf($g)]['color'], 'strong' => \in_array($g, $scores['top'], true)];
        }
        $families = [];
        foreach (Content::get('gifts.families') as $key => $f) {
            $vals = array_map(fn ($g) => $scores['scores'][$g] ?? 0, $f['gifts']);
            $families[$key] = $f + ['avg' => (int) round(array_sum($vals) / \count($vals))];
        }
        $top = array_map(fn ($g) => Content::get('gifts.gifts')[$g] + Content::get('gifts.profiles')[$g] + ['score' => $scores['scores'][$g], 'code' => $g, 'strength' => self::giftStrength($scores['scores'][$g]),
            'family' => Content::get('gifts.families')[GiftProfiles::familyOf($g)]], $scores['top']);
        uasort($families, fn ($a, $b) => $b['avg'] <=> $a['avg']);

        return ['gifts' => ['top' => $top, 'bars' => $bars, 'families' => $families, 'mainFamily' => array_key_first($families),
            'charts' => ['bars' => ReportCharts::uri(ReportCharts::hbars($rows, 150))]]];
    }
}
