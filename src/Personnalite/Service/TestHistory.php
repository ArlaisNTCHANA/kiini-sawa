<?php

namespace App\Personnalite\Service;

use App\Compte\Entity\User;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Psychometrie\DiscBank;
use App\Personnalite\Psychometrie\ReportCharts;
use App\Personnalite\Repository\AssessmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Historique des passations et évolution dans le temps. Chaque passation garde ses scores (figés) ; les textes, eux,
 * suivent le contenu actuel (Content::get).
 *
 * Séries : disc, bigfive (profil complet + Big Five essentiel), type (type + profil complet), enneagramme,
 * dons-spirituels, equilibre, hygiene.
 */
final class TestHistory
{
    public const SERIES = [
        'disc' => ['name' => 'DISC', 'instruments' => ['disc']],
        'bigfive' => ['name' => 'Big Five', 'instruments' => ['profil-complet', 'big-five']],
        'type' => ['name' => 'Type de fonctionnement', 'instruments' => ['type', 'profil-complet']],
        'enneagramme' => ['name' => 'Ennéagramme', 'instruments' => ['enneagramme']],
        'dons-spirituels' => ['name' => 'Dons spirituels', 'instruments' => ['dons-spirituels']],
        'equilibre' => ['name' => 'Équilibre de Vie', 'profileTest' => 'EQUILIBRE'],
        'hygiene' => ['name' => 'Hygiène de Vie', 'profileTest' => 'HYGIENE'],
    ];

    /** Instrument d'une passation => série principale (pour « Vos passations » dans un rapport). */
    public const SERIES_OF = ['disc' => 'disc', 'profil-complet' => 'bigfive', 'big-five' => 'bigfive', 'type' => 'type',
        'enneagramme' => 'enneagramme', 'dons-spirituels' => 'dons-spirituels', 'EQUILIBRE' => 'equilibre', 'HYGIENE' => 'hygiene'];

    public function __construct(private readonly AssessmentRepository $assessments, private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Passations d'une série, de la plus ancienne à la plus récente.
     *
     * @return list<array{id: int, date: \DateTimeInterface, label: string, url: string, scores: array}>
     */
    public function entries(User $user, string $series): array
    {
        $def = self::SERIES[$series] ?? null;
        if (!$def) {
            return [];
        }
        $out = [];
        if (isset($def['profileTest'])) {
            $tests = $this->em->getRepository(ProfileTest::class)->findBy(['user' => $user, 'type' => $def['profileTest']], ['date' => 'ASC', 'id' => 'ASC']);
            foreach ($tests as $t) {
                $data = json_decode((string) $t->getResult(), true) ?: [];
                $values = 'equilibre' === $series ? $data : ($data['axes'] ?? []);
                $avg = $values ? array_sum($values) / \count($values) : 0;
                $out[] = ['id' => $t->getId(), 'date' => $t->getDate(), 'label' => 'Score global : '.number_format($avg, 1, ',', ' ').' / 10',
                    'url' => '/mes-tests/'.strtolower($def['profileTest']).'/resultats/'.$t->getId(), 'scores' => $values];
            }

            return $out;
        }
        $list = array_reverse(array_filter($this->assessments->findForUser($user), fn (Assessment $a) => \in_array($a->getInstrument(), $def['instruments'], true)));
        foreach ($list as $a) {
            $scores = $a->getScores();
            if ('type' === $series && empty($scores['type'])) {
                continue;
            }
            if ('bigfive' === $series && \count($scores['domains'] ?? []) < 5) {
                continue;
            }
            $out[] = ['id' => $a->getId(), 'date' => $a->getTakenAt(), 'label' => $this->label($series, $scores), 'url' => '/mes-tests/rapport/'.$a->getId(), 'scores' => $scores];
        }

        return array_values($out);
    }

    /** Résultat principal d'une passation, en quelques mots. */
    public function label(string $series, array $scores): string
    {
        return match ($series) {
            'disc' => ($code = $scores['primary'].($scores['secondary'] ?? '')).' · '.(Content::get('disc.profiles')[$code]['name'] ?? ''),
            'bigfive' => implode(' · ', array_map(fn ($d) => $d.' '.round($scores['domains'][$d]['score']), array_keys($scores['domains']))),
            'type' => $scores['type']['code'].' · '.(Content::get('type.types')[$scores['type']['code']][0] ?? ''),
            'enneagramme' => 'Type '.$scores['type'].' · '.(Content::get('enn.types')[$scores['type']]['name'] ?? ''),
            'dons-spirituels' => implode(' · ', array_map(fn ($g) => Content::get('gifts.gifts')[$g]['name'] ?? $g, $scores['top'])),
            default => '',
        };
    }

    /** Séries ayant au moins deux passations (pour proposer « Mon évolution »). */
    public function withHistory(User $user): array
    {
        $out = [];
        foreach (array_keys(self::SERIES) as $s) {
            if (\count($this->entries($user, $s)) >= 2) {
                $out[$s] = self::SERIES[$s]['name'];
            }
        }

        return $out;
    }

    /** Données de la page « Mon évolution » d'une série. */
    public function evolution(User $user, string $series): array
    {
        $entries = $this->entries($user, $series);
        $out = ['series' => $series, 'name' => self::SERIES[$series]['name'], 'entries' => $entries, 'enough' => \count($entries) >= 2];
        if (!$out['enough']) {
            return $out;
        }
        $labels = array_map(fn ($e) => $e['date']->format('d/m/y'), $entries);
        $first = $entries[0]['scores'];
        $last = end($entries)['scores'];

        return $out + match ($series) {
            'disc' => $this->disc($entries, $labels, $first, $last),
            'bigfive' => $this->bigfive($entries, $labels, $first, $last),
            'equilibre', 'hygiene' => $this->self($series, $entries, $labels, $first, $last),
            default => $this->categorical($series, $entries),
        };
    }

    private function disc(array $entries, array $labels, array $first, array $last): array
    {
        $dims = Content::get('disc.dimensions');
        $nat = $ad = [];
        foreach (DiscBank::LETTERS as $l) {
            $nat[] = ['name' => $l.' · '.$dims[$l]['name'], 'color' => $dims[$l]['color'], 'values' => array_map(fn ($e) => (float) $e['scores']['natural'][$l], $entries)];
            $ad[] = ['name' => $l.' · '.$dims[$l]['name'], 'color' => $dims[$l]['color'], 'values' => array_map(fn ($e) => (float) $e['scores']['adapted'][$l], $entries)];
        }
        $gap = [['name' => 'Écart naturel / adapté', 'color' => '#0F172A', 'values' => array_map(fn ($e) => (float) $e['scores']['gap'], $entries)]];
        $d = $last['gap'] - $first['gap'];
        $reading = [];
        $reading[] = $first['primary'] === $last['primary']
            ? sprintf('Votre nature reste %s : c\'est attendu, le profil naturel change peu.', mb_strtolower($dims[$last['primary']]['name']))
            : sprintf('Votre dimension naturelle dominante est passée de %s à %s : vérifiez si un contexte particulier a pu influencer vos réponses.', mb_strtolower($dims[$first['primary']]['name']), mb_strtolower($dims[$last['primary']]['name']));
        $reading[] = match (true) {
            $d <= -6 => sprintf('Votre écart d\'adaptation a diminué (%d → %d points) : votre contexte vous demande moins d\'effort, ou vous y avez trouvé votre place.', $first['gap'], $last['gap']),
            $d >= 6 => sprintf('Votre écart d\'adaptation a augmenté (%d → %d points) : votre contexte vous demande davantage. Veillez à votre énergie.', $first['gap'], $last['gap']),
            default => sprintf('Votre écart d\'adaptation est stable (%d → %d points).', $first['gap'], $last['gap']),
        };
        if ($first['adaptedPrimary'] !== $last['adaptedPrimary']) {
            $reading[] = sprintf('En contexte, on vous voit désormais plutôt %s (auparavant %s).', mb_strtolower($dims[$last['adaptedPrimary']]['name']), mb_strtolower($dims[$first['adaptedPrimary']]['name']));
        }

        return ['reading' => $reading, 'charts' => [
            ['title' => 'Profil naturel', 'img' => ReportCharts::uri(ReportCharts::lines($labels, $nat, 60))],
            ['title' => 'Profil adapté', 'img' => ReportCharts::uri(ReportCharts::lines($labels, $ad, 60))],
            ['title' => 'Écart d\'adaptation', 'img' => ReportCharts::uri(ReportCharts::lines($labels, $gap, 50))],
        ]];
    }

    private function bigfive(array $entries, array $labels, array $first, array $last): array
    {
        $doms = Content::get('bigfive.levels');
        $names = \App\Personnalite\Psychometrie\IpipItemBank::DOMAINS;
        $series = [];
        $changes = [];
        foreach (['E', 'O', 'A', 'C', 'N'] as $d) {
            $series[] = ['name' => $names[$d], 'color' => $doms[$d]['color'], 'values' => array_map(fn ($e) => isset($e['scores']['domains'][$d]) ? (float) $e['scores']['domains'][$d]['score'] : null, $entries)];
            $delta = round(($last['domains'][$d]['score'] ?? 0) - ($first['domains'][$d]['score'] ?? 0));
            if (abs($delta) >= 8) {
                $changes[] = sprintf('%s : %s%d points.', $names[$d], $delta > 0 ? '+' : '', $delta);
            }
        }
        $reading = $changes ? array_merge(['Les dimensions qui ont le plus évolué :'], $changes) : ['Votre personnalité est stable entre vos passations : c\'est le cas le plus fréquent chez l\'adulte.'];

        return ['reading' => $reading, 'charts' => [['title' => 'Vos cinq dimensions', 'img' => ReportCharts::uri(ReportCharts::lines($labels, $series, 100))]]];
    }

    private function self(string $series, array $entries, array $labels, array $first, array $last): array
    {
        $profiles = Content::get('equilibre' === $series ? 'self.equilibre' : 'self.hygiene');
        $rows = [];
        $lines = [];
        foreach ($profiles as $key => $p) {
            $a = (float) ($first[$key] ?? 0);
            $b = (float) ($last[$key] ?? 0);
            $rows[] = ['label' => $p['label'], 'color' => $p['color'], 'before' => $a, 'after' => $b, 'delta' => round($b - $a, 1)];
            $lines[] = ['name' => $p['label'], 'color' => $p['color'], 'values' => array_map(fn ($e) => isset($e['scores'][$key]) ? (float) $e['scores'][$key] : null, $entries)];
        }
        usort($rows, fn ($x, $y) => $y['delta'] <=> $x['delta']);
        $avg = fn (array $v) => $v ? array_sum($v) / \count($v) : 0;
        $d = round($avg($last) - $avg($first), 1);
        $reading = [sprintf('Score global : %s → %s / 10 (%s%s).', number_format($avg($first), 1, ',', ' '), number_format($avg($last), 1, ',', ' '), $d >= 0 ? '+' : '', number_format($d, 1, ',', ' '))];
        if ($rows[0]['delta'] > 0) {
            $reading[] = sprintf('Votre plus beau progrès : %s (+%s).', mb_strtolower($rows[0]['label']), number_format($rows[0]['delta'], 1, ',', ' '));
        }
        $worst = end($rows);
        if ($worst['delta'] < 0) {
            $reading[] = sprintf('À surveiller : %s (%s).', mb_strtolower($worst['label']), number_format($worst['delta'], 1, ',', ' '));
        }

        return ['reading' => $reading, 'rows' => $rows, 'charts' => [['title' => 'Vos domaines au fil du temps', 'img' => ReportCharts::uri(ReportCharts::lines($labels, $lines, 10))]]];
    }

    /** Ennéagramme, type, dons : un résultat par date, en signalant les changements. */
    private function categorical(string $series, array $entries): array
    {
        $timeline = [];
        $prev = null;
        foreach ($entries as $e) {
            $timeline[] = ['date' => $e['date'], 'label' => $e['label'], 'changed' => null !== $prev && $prev !== $e['label'], 'url' => $e['url']];
            $prev = $e['label'];
        }
        $reading = [];
        if ('dons-spirituels' === $series) {
            $tops = array_map(fn ($e) => $e['scores']['top'], $entries);
            $stable = array_values(array_intersect(...$tops));
            $names = fn (array $codes) => implode(', ', array_map(fn ($g) => mb_strtolower(Content::get('gifts.gifts')[$g]['name'] ?? $g), $codes));
            $reading[] = $stable ? 'Présents à chaque passation : '.$names($stable).'. Ce sont vos dons les plus constants.' : 'Vos dons principaux varient d\'une passation à l\'autre : le service et le regard de vos proches vous aideront à discerner.';
            $new = array_values(array_diff(end($tops), $tops[0]));
            if ($new) {
                $reading[] = 'Apparu récemment : '.$names($new).'.';
            }
        } else {
            $changes = \count(array_filter($timeline, fn ($t) => $t['changed']));
            $reading[] = 0 === $changes ? 'Votre résultat est le même à chaque passation : il est solide.'
                : ('enneagramme' === $series ? 'Votre type a changé entre vos passations : relisez la peur profonde de chaque type, c\'est souvent elle qui tranche.' : 'Votre type a changé : une ou plusieurs préférences sont sans doute peu nettes chez vous.');
        }

        return ['reading' => $reading, 'timeline' => $timeline, 'charts' => []];
    }
}
