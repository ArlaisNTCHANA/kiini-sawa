<?php

namespace App\Personnalite\Service;

use App\Compte\Entity\User;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Psychometrie\ReportBuilder;
use App\Personnalite\Psychometrie\ReportCharts;
use App\Personnalite\Repository\AssessmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * « Mon profil KIINI SAWA » : synthèse croisée de tous les tests passés (DISC, Big Five, type, ennéagramme, dons,
 * équilibre de vie). Convergences entre tests, zone de vocation, ressources et fragilités, priorités de croissance.
 */
final class ProfileSynthesis
{
    public const TESTS = [
        'disc' => 'DISC', 'bigfive' => 'Profil complet ou Big Five', 'type' => 'Type de fonctionnement',
        'enneagramme' => 'Ennéagramme', 'dons' => 'Dons spirituels',
    ];
    private const SLUGS = ['disc' => 'disc', 'bigfive' => 'profil-complet', 'type' => 'type', 'enneagramme' => 'enneagramme', 'dons' => 'dons-spirituels'];
    private const COLORS = ['disc' => '#F43F5E', 'bigfive' => '#2E6B53', 'type' => '#6366F1'];

    public function __construct(
        private readonly AssessmentRepository $assessments,
        private readonly ReportBuilder $reports,
        private readonly SelfAssessmentReport $selfReports,
        private readonly EntityManagerInterface $em,
        private readonly InsightEngine $insights,
    ) {
    }

    public function build(User $user): array
    {
        $r = $this->collect($user);
        $done = array_keys(array_filter($r));
        $missing = array_diff_key(self::TESTS, array_flip($done));

        $out = ['parts' => $r, 'done' => $done, 'missing' => $missing, 'slugs' => self::SLUGS, 'count' => \count($done), 'enough' => \count($done) >= 2,
            'assessment' => ['takenAt' => new \DateTime()], 'quality' => []];
        if (!$out['enough']) {
            return $out;
        }

        $convergence = $this->convergence($r);
        $life = $this->life($user);

        return $out + [
            'identity' => $this->identity($r),
            'convergence' => $convergence,
            'vocation' => $this->vocation($r),
            'fragile' => $this->fragile($r),
            'resources' => $this->resources($r),
            'priorities' => $this->priorities($r),
            'life' => $life,
            'insights' => $this->insights->analyze($r, $convergence, $life),
        ];
    }

    /**
     * Lectures croisées qui concernent un test donné (au moins deux tests en jeu, dont celui-ci), pour le rapport de ce test.
     *
     * @param string $key clé de test : disc, bigfive, type, enneagramme, dons
     *
     * @return list<array<string, mixed>>
     */
    public function insightsFor(User $user, string $key, int $max = 2): array
    {
        $r = $this->collect($user);
        if (\count(array_filter($r)) < 2) {
            return [];
        }
        $found = $this->insights->analyze($r, $this->convergence($r), $this->life($user));

        return \array_slice(array_values(array_filter($found, fn ($i) => \in_array($key, $i['sources'], true) && \count($i['sources']) >= 2)), 0, $max);
    }

    /** Derniers rapports de chaque test. */
    private function collect(User $user): array
    {
        $build = fn (?object $a) => $a ? $this->reports->build($a) : null;
        $bigfive = null;
        foreach (['profil-complet', 'big-five'] as $slug) {
            $a = $this->assessments->latest($user, $slug);
            if ($a && (!$bigfive || $a->getTakenAt() > $bigfive->getTakenAt())) {
                $bigfive = $a;
            }
        }
        $bf = $build($bigfive);
        $type = ($bf['typeX'] ?? null) ? $bf : $build($this->assessments->latest($user, 'type'));

        return [
            'disc' => $build($this->assessments->latest($user, 'disc')),
            'bigfive' => ($bf['bf'] ?? null) ? $bf : null,
            'type' => ($type['typeX'] ?? null) ? $type : null,
            'enneagramme' => $build($this->assessments->latest($user, 'enneagramme')),
            'dons' => $build($this->assessments->latest($user, 'dons-spirituels')),
        ];
    }

    private function identity(array $r): array
    {
        $cards = [];
        if ($r['disc']) {
            $c = $r['disc']['disc']['combo'];
            $cards[] = ['icon' => 'compass', 'label' => 'Votre manière d\'agir', 'title' => $c['code'].' · '.$c['name'], 'text' => $c['tagline'], 'color' => $r['disc']['disc']['profile']['color']];
        }
        if ($r['enneagramme']) {
            $e = $r['enneagramme']['enneagram'];
            $cards[] = ['icon' => 'flame', 'label' => 'Ce qui vous pousse', 'title' => 'Type '.$e['type'].' · '.$e['profile']['name'], 'text' => $e['profile']['motivation'], 'color' => $e['center']['color']];
        }
        if ($r['bigfive']) {
            $cards[] = ['icon' => 'sun', 'label' => 'Votre personnalité', 'title' => $r['bigfive']['bf']['headline'], 'text' => 'Vos trois traits les plus marqués (Big Five).', 'color' => '#2E6B53'];
        }
        if ($r['type']) {
            $t = $r['type'];
            $cards[] = ['icon' => 'target', 'label' => 'Votre fonctionnement', 'title' => $t['type']['code'].' · '.$t['type']['name'], 'text' => $t['typeX']['tagline'], 'color' => $t['typeX']['family']['color']];
        }
        if ($r['dons']) {
            $g = $r['dons']['gifts'];
            $cards[] = ['icon' => 'star', 'label' => 'Vos dons', 'title' => implode(' · ', array_column($g['top'], 'name')), 'text' => $g['families'][$g['mainFamily']]['name'], 'color' => $g['top'][0]['family']['color']];
        }
        $headline = implode(' · ', array_filter([
            $r['disc']['disc']['combo']['name'] ?? null,
            $r['enneagramme']['enneagram']['profile']['name'] ?? null,
            $r['dons']['gifts']['top'][0]['name'] ?? null,
        ])) ?: ($cards[0]['title'] ?? 'Mon profil');

        return ['cards' => $cards, 'headline' => $headline];
    }

    /** Chaque test se place sur trois axes (-1 à 1) : on regarde s'ils se rejoignent. */
    private function convergence(array $r): array
    {
        $clamp = fn (float $v) => max(-1, min(1, $v));
        $m = ['relation' => [], 'rythme' => [], 'structure' => []];
        if ($r['disc']) {
            $n = $r['disc']['disc']['natural'];
            $m['relation'][] = ['DISC', $clamp(1.6 * (($n['I'] + $n['S']) - ($n['D'] + $n['C'])) / 100)];
            $m['rythme'][] = ['DISC', $clamp(1.6 * (($n['D'] + $n['I']) - ($n['S'] + $n['C'])) / 100)];
            $m['structure'][] = ['DISC', $clamp(($n['C'] - 25) / 25)];
        }
        if ($r['bigfive']) {
            $d = $r['bigfive']['bf']['domains'];
            isset($d['A']) && $m['relation'][] = ['Big Five', $clamp(($d['A']['value'] - 50) / 40)];
            isset($d['E']) && $m['rythme'][] = ['Big Five', $clamp(($d['E']['value'] - 50) / 40)];
            isset($d['C']) && $m['structure'][] = ['Big Five', $clamp(($d['C']['value'] - 50) / 40)];
        }
        if ($r['type']) {
            $axes = array_values($r['type']['type']['axes']);
            $sign = fn (array $a, string $plus) => ($a['letter'] === $plus ? 1 : -1) * $a['clarity'] / 100;
            $m['rythme'][] = ['Type', $clamp($sign($axes[0], 'E'))];
            $m['relation'][] = ['Type', $clamp($sign($axes[2], 'F'))];
            $m['structure'][] = ['Type', $clamp($sign($axes[3], 'J'))];
        }
        $texts = Content::get('synth.axes');
        $axes = [];
        foreach ($m as $key => $list) {
            if (!$list) {
                continue;
            }
            $values = array_column($list, 1);
            $mean = array_sum($values) / \count($values);
            $spread = max($values) - min($values);
            $t = $texts[$key];
            $axes[$key] = [
                'left' => $t['left'], 'right' => $t['right'], 'mean' => $mean,
                'markers' => array_map(fn ($x) => ['name' => $x[0], 'value' => $x[1], 'color' => self::COLORS[array_search($x[0], ['disc' => 'DISC', 'bigfive' => 'Big Five', 'type' => 'Type'], true)]], $list),
                'reading' => ucfirst($mean > 0.25 ? $t['rightText'] : ($mean < -0.25 ? $t['leftText'] : $t['midText'])).'.',
                'agree' => \count($values) < 2 ? null : $spread <= 0.7,
            ];
        }

        return ['axes' => $axes, 'chart' => $axes ? ReportCharts::uri(ReportCharts::convergence(array_values($axes))) : null,
            'divergent' => array_keys(array_filter($axes, fn ($a) => false === $a['agree']))];
    }

    private function vocation(array $r): ?array
    {
        if (!$r['disc'] || !$r['dons']) {
            return null;
        }
        $primary = $r['disc']['disc']['primary'];
        $family = $r['dons']['gifts']['mainFamily'];
        $v = Content::get('synth.vocations')[$primary][$family];

        return $v + ['familyName' => $r['dons']['gifts']['families'][$family]['name'], 'dimension' => $r['disc']['disc']['profile']['name'],
            'gifts' => array_column($r['dons']['gifts']['top'], 'name'), 'motivation' => $r['enneagramme']['enneagram']['profile']['motivation'] ?? null];
    }

    private function fragile(array $r): array
    {
        $items = [];
        $r['disc'] && $items[] = ['DISC', 'Votre crainte : '.lcfirst($r['disc']['disc']['profile']['fear']).'.'];
        $r['enneagramme'] && $items[] = ['Ennéagramme', 'Votre peur profonde : '.lcfirst($r['enneagramme']['enneagram']['profile']['fear'])];
        if ($r['bigfive'] && 'high' === ($r['bigfive']['bf']['domains']['N']['level'] ?? null)) {
            $items[] = ['Big Five', 'Vous ressentez fortement le stress : vos temps de repos et de confiance sont essentiels.'];
        }
        $r['disc'] && $items[] = ['DISC', 'Sous pression : '.lcfirst($r['disc']['disc']['combo']['signs'])];
        $r['enneagramme'] && $items[] = ['Ennéagramme', 'Signal d\'alerte : '.lcfirst($r['enneagramme']['enneagram']['x']['signs'])];
        $r['type'] && $items[] = ['Type', 'Sous pression : '.lcfirst($r['type']['typeX']['stress'])];

        return $items;
    }

    private function resources(array $r): array
    {
        $items = [];
        $r['disc'] && $items[] = ['DISC', 'Ce qui vous ressource : '.lcfirst($r['disc']['disc']['combo']['recharge'])];
        $r['enneagramme'] && $items[] = ['Ennéagramme', 'Quand vous grandissez : '.lcfirst($r['enneagramme']['enneagram']['x']['growthText'])];
        foreach (\array_slice($r['bigfive']['strengths'] ?? [], 0, 2) as $f) {
            $items[] = ['Big Five', $f['label'].' : '.lcfirst($f['text'])];
        }
        $r['dons'] && $items[] = ['Dons', 'Vos dons : '.implode(', ', array_map('mb_strtolower', array_column($r['dons']['gifts']['top'], 'name'))).'.'];

        return $items;
    }

    /** Trois actions concrètes, une par test différent. */
    private function priorities(array $r): array
    {
        $out = [];
        $r['disc'] && $out[] = ['source' => 'DISC', 'slug' => 'disc', 'text' => $r['disc']['disc']['combo']['actions'][0]];
        $r['enneagramme'] && $out[] = ['source' => 'Ennéagramme', 'slug' => 'enneagramme', 'text' => $r['enneagramme']['enneagram']['x']['actions'][0]];
        $r['dons'] && $out[] = ['source' => 'Dons spirituels', 'slug' => 'dons-spirituels', 'text' => $r['dons']['gifts']['top'][0]['action']];
        if (\count($out) < 3 && $r['bigfive']) {
            $marked = $r['bigfive']['bf']['marked'];
            $out[] = ['source' => 'Big Five', 'slug' => 'profil-complet', 'text' => reset($marked)['lx']['grow']];
        }
        if (\count($out) < 3 && $r['type']) {
            $out[] = ['source' => 'Type', 'slug' => 'type', 'text' => $r['type']['typeX']['actions'][0]];
        }

        return \array_slice($out, 0, 3);
    }

    private function life(User $user): ?array
    {
        $t = $this->em->getRepository(ProfileTest::class)->findOneBy(['user' => $user, 'type' => 'EQUILIBRE'], ['date' => 'DESC', 'id' => 'DESC']);
        $rep = $t ? $this->selfReports->build($t) : null;

        return $rep ? ['avg' => $rep['summary']['avg'], 'priorities' => array_column($rep['summary']['priorities'], 'label'), 'supports' => array_column($rep['summary']['supports'], 'label')] : null;
    }
}
