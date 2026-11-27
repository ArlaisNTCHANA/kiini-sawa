<?php

namespace App\Boussole\Service;

use App\Boussole\Entity\BoussoleResult;
use App\PlanDeVie\Service\PlanCatalog;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Chiffres de la Boussole 360 pour l'administrateur (27/11/2026) : combien la passent, combien de visiteurs la gardent
 * en créant un compte, et ce que disent les passations des membres (plan le plus fort, plan à soigner, moyennes).
 * Des totaux seulement : jamais un nom ni les réponses d'une personne.
 */
final class BoussoleStats
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly Connection $db)
    {
    }

    /** @return array<string, mixed> */
    public function report(int $days = 30): array
    {
        $since = new \DateTimeImmutable("-{$days} days midnight");
        $sinceDay = $since->format('Y-m-d');
        $one = fn (string $sql, array $p = []): int => (int) $this->db->fetchOne($sql, $p);

        $visitors = $one('SELECT COALESCE(SUM(visitors), 0) FROM boussole_daily WHERE day >= :d', ['d' => $sinceDay]);
        $newMembers = $one('SELECT COALESCE(SUM(new_members), 0) FROM boussole_daily WHERE day >= :d', ['d' => $sinceDay]);
        $returning = $one('SELECT COALESCE(SUM(returning_members), 0) FROM boussole_daily WHERE day >= :d', ['d' => $sinceDay]);
        $countingSince = $this->db->fetchOne('SELECT MIN(day) FROM boussole_daily') ?: null;

        $passes = $one('SELECT COUNT(*) FROM boussole_result WHERE created_at >= :s', ['s' => $since->format('Y-m-d H:i:s')]);
        $members = $one('SELECT COUNT(DISTINCT user_id) FROM boussole_result WHERE created_at >= :s', ['s' => $since->format('Y-m-d H:i:s')]);
        $allMembers = $one('SELECT COUNT(DISTINCT user_id) FROM boussole_result');
        $repeat = $one('SELECT COUNT(*) FROM (SELECT user_id FROM boussole_result GROUP BY user_id HAVING COUNT(*) > 1) r');
        $mobile = $one("SELECT COUNT(*) FROM boussole_result WHERE created_at >= :s AND source = 'mobile'", ['s' => $since->format('Y-m-d H:i:s')]);

        // Ce que disent les passations de la période (la plus récente de chaque membre)
        $latest = $this->em->createQueryBuilder()->select('r')->from(BoussoleResult::class, 'r')
            ->where('r.createdAt >= :s')->setParameter('s', $since)->orderBy('r.createdAt', 'DESC')->getQuery()->getResult();
        $seen = [];
        $sum = array_fill_keys(BoussoleEngine::PLAN_ORDER, 0);
        $strongest = array_fill_keys(BoussoleEngine::PLAN_ORDER, 0);
        $priority = array_fill_keys(BoussoleEngine::PLAN_ORDER, 0);
        $coherence = 0;
        foreach ($latest as $r) {
            $uid = $r->getUser()->getId();
            if (isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            foreach ($r->getScores() as $k => $v) {
                $sum[$k] += $v;
            }
            $strongest[$r->getStrongestKey()] = ($strongest[$r->getStrongestKey()] ?? 0) + 1;
            $priority[$r->getPriorityKey()] = ($priority[$r->getPriorityKey()] ?? 0) + 1;
            $coherence += $r->getCoherence();
        }
        $n = \count($seen);
        $plans = [];
        foreach (BoussoleEngine::PLAN_ORDER as $k) {
            $plans[] = ['label' => PlanCatalog::label($k), 'average' => $n > 0 ? (int) round($sum[$k] / $n) : null, 'strongest' => $strongest[$k], 'priority' => $priority[$k]];
        }

        return [
            'days' => $days,
            'visitors' => $visitors, 'newMembers' => $newMembers, 'returning' => $returning, 'countingSince' => $countingSince,
            'conversion' => $visitors > 0 ? (int) round($newMembers * 100 / $visitors) : null,
            'passes' => $passes, 'members' => $members, 'allMembers' => $allMembers, 'repeat' => $repeat, 'mobile' => $mobile,
            'coherence' => $n > 0 ? (int) round($coherence / $n) : null,
            'sample' => $n,
            'plans' => $plans,
        ];
    }
}
