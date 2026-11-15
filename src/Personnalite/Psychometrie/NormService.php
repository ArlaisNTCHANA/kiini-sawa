<?php

namespace App\Personnalite\Psychometrie;

use App\Personnalite\Entity\Assessment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Normes et fidélité calculées sur les réponses réelles des utilisateurs de KIINI SAWA.
 *
 * - Percentiles : dès que MIN_NORM_SAMPLE personnes ont un score sur une échelle (loi normale ajustée).
 *   Avant ce seuil, les rapports affichent le score 0-100 sans le comparer à la population.
 * - Fidélité (alpha de Cronbach) de chaque facette et de chaque type d'ennéagramme / don, pour l'admin.
 */
final class NormService
{
    public const MIN_NORM_SAMPLE = 200;

    public function __construct(private readonly EntityManagerInterface $em, private readonly CacheInterface $cache)
    {
    }

    /** @return array<string, array{mean: float, sd: float, n: int}> échelle (« N », « N1 »…) => norme */
    public function norms(): array
    {
        return $this->cache->get('psychometrics_norms_v1', function (ItemInterface $item): array {
            $item->expiresAfter(3600 * 6);
            $values = [];
            foreach ($this->latestIpipByUser() as $scores) {
                foreach ($scores['domains'] ?? [] as $d => $v) {
                    $values[$d][] = $v['score'];
                }
                foreach ($scores['facets'] ?? [] as $f => $v) {
                    if (($v['items'] ?? 0) >= 4) {
                        $values[$f][] = $v['score'];
                    }
                }
            }
            $norms = [];
            foreach ($values as $scale => $list) {
                $n = \count($list);
                $mean = array_sum($list) / max(1, $n);
                $sd = $n > 1 ? sqrt(array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $list)) / ($n - 1)) : 0.0;
                $norms[$scale] = ['mean' => round($mean, 2), 'sd' => round($sd, 2), 'n' => $n];
            }

            return $norms;
        });
    }

    /** Percentile (1-99) ou null si l'échantillon est encore trop petit. */
    public function percentile(string $scale, float $score): ?int
    {
        $norm = $this->norms()[$scale] ?? null;
        if (null === $norm || $norm['n'] < self::MIN_NORM_SAMPLE || $norm['sd'] <= 0) {
            return null;
        }

        return max(1, min(99, (int) round($this->normalCdf(($score - $norm['mean']) / $norm['sd']) * 100)));
    }

    /**
     * Alpha de Cronbach par échelle, sur les passations complètes.
     *
     * @return array<string, array{alpha: ?float, n: int, label: string}>
     */
    public function reliability(): array
    {
        $groups = [];
        foreach (IpipItemBank::FACETS as $f => $label) {
            $groups['ipip:'.$f] = ['label' => $label, 'items' => array_map(fn ($id) => ['key' => 'ipip:'.$id, 'rev' => -1 === IpipItemBank::ITEMS[$id][1]], IpipItemBank::itemsOfFacet($f))];
        }
        foreach (EnneagramBank::TYPES as $t => $def) {
            $groups['enn:'.$t] = ['label' => 'Ennéagramme '.$t.' — '.$def['name'], 'items' => array_map(fn ($id) => ['key' => 'enn:'.$id, 'rev' => false], array_keys(array_filter(EnneagramBank::ITEMS, fn ($i) => $i[0] === $t)))];
        }
        foreach (SpiritualGiftsBank::GIFTS as $g => $def) {
            $groups['don:'.$g] = ['label' => 'Don : '.$def['name'], 'items' => array_map(fn ($id) => ['key' => 'don:'.$id, 'rev' => false], array_keys(array_filter(SpiritualGiftsBank::ITEMS, fn ($i) => $i[0] === $g)))];
        }

        $rows = $this->em->getConnection()->fetchFirstColumn('SELECT answers FROM assessment');
        $all = array_map(fn ($json) => json_decode($json, true) ?: [], $rows);

        $result = [];
        foreach ($groups as $code => $g) {
            $matrix = [];
            foreach ($all as $answers) {
                $row = [];
                foreach ($g['items'] as $it) {
                    if (!isset($answers[$it['key']])) {
                        continue 2;
                    }
                    $row[] = $it['rev'] ? 6 - $answers[$it['key']] : $answers[$it['key']];
                }
                $matrix[] = $row;
            }
            $result[$code] = ['alpha' => \count($matrix) >= 30 ? $this->cronbach($matrix) : null, 'n' => \count($matrix), 'label' => $g['label']];
        }

        return $result;
    }

    private function latestIpipByUser(): array
    {
        $rows = $this->em->getRepository(Assessment::class)->createQueryBuilder('a')
            ->where('a.instrument IN (:i)')->setParameter('i', ['profil-complet', 'big-five', 'type', 'style'])
            ->orderBy('a.takenAt', 'ASC')->getQuery()->getResult();
        $byUser = [];
        foreach ($rows as $a) {
            if ($a->getQuality()['reliable'] ?? true) {
                $byUser[$a->getUser()->getId()] = $a->getScores();
            }
        }

        return $byUser;
    }

    private function cronbach(array $matrix): ?float
    {
        $k = \count($matrix[0] ?? []);
        if ($k < 2) {
            return null;
        }
        $variance = function (array $xs): float {
            $m = array_sum($xs) / \count($xs);

            return array_sum(array_map(fn ($x) => ($x - $m) ** 2, $xs)) / max(1, \count($xs) - 1);
        };
        $itemVar = 0.0;
        for ($j = 0; $j < $k; ++$j) {
            $itemVar += $variance(array_column($matrix, $j));
        }
        $totalVar = $variance(array_map('array_sum', $matrix));

        return $totalVar > 0 ? round($k / ($k - 1) * (1 - $itemVar / $totalVar), 2) : null;
    }

    /** Fonction de répartition de la loi normale (approximation d'Abramowitz et Stegun). */
    private function normalCdf(float $z): float
    {
        $t = 1 / (1 + 0.2316419 * abs($z));
        $d = 0.3989423 * exp(-$z * $z / 2);
        $p = $d * $t * (0.3193815 + $t * (-0.3565638 + $t * (1.781478 + $t * (-1.821256 + $t * 1.330274))));

        return $z > 0 ? 1 - $p : $p;
    }
}
