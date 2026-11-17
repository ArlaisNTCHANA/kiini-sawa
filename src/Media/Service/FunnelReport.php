<?php

namespace App\Media\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * Entonnoir d'acquisition : visites de la vitrine, inscriptions, emails vérifiés, onboarding terminé, parcours commencé,
 * abonnés PRO. Les visites sont comptées par jour (compteur sans donnée personnelle, robots écartés).
 */
final class FunnelReport
{
    private const BOT = '/bot|crawl|spider|slurp|preview|monitor|uptime|curl|wget|headless|facebookexternalhit|whatsapp/i';

    public function __construct(private readonly Connection $db)
    {
    }

    /** Compte une visite de la vitrine (hors robots et requêtes de pré-chargement). */
    public function countVisit(Request $request): void
    {
        if (!$request->isMethod('GET') || preg_match(self::BOT, (string) $request->headers->get('User-Agent', ''))
            || \in_array($request->headers->get('Purpose'), ['prefetch', 'preview'], true) || $request->headers->has('Sec-Purpose')) {
            return;
        }
        $this->db->executeStatement('INSERT INTO daily_visit (day, visits) VALUES (CURRENT_DATE, 1) ON DUPLICATE KEY UPDATE visits = visits + 1');
    }

    /**
     * @return array{days: int, steps: list<array{label: string, count: int, rate: ?float}>, since: string, visitsSince: ?string}
     */
    public function funnel(int $days, \DateTimeImmutable $today): array
    {
        $since = $today->modify("-{$days} days")->format('Y-m-d');
        $one = fn (string $sql): int => (int) $this->db->fetchOne($sql, ['since' => $since]);

        $visits = $one('SELECT COALESCE(SUM(visits), 0) FROM daily_visit WHERE day >= :since');
        $firstDay = $this->db->fetchOne('SELECT MIN(day) FROM daily_visit') ?: null;
        $signups = $one("SELECT COUNT(*) FROM `user` WHERE created_at >= :since AND roles NOT LIKE '%ROLE_ADMIN%'");
        $verified = $one("SELECT COUNT(*) FROM `user` WHERE created_at >= :since AND is_verified = 1 AND roles NOT LIKE '%ROLE_ADMIN%'");
        $onboarded = $one("SELECT COUNT(*) FROM `user` WHERE created_at >= :since AND is_verified = 1 AND is_onboarding_completed = 1 AND roles NOT LIKE '%ROLE_ADMIN%'");
        $journey = $one('SELECT COUNT(*) FROM journey_progress jp JOIN `user` u ON u.id = jp.user_id WHERE u.created_at >= :since');
        $pro = $one("SELECT COUNT(DISTINCT s.user_id) FROM subscription s JOIN `user` u ON u.id = s.user_id WHERE u.created_at >= :since AND s.tier <> 'FREE' AND s.status = 'ACTIVE'");

        $rows = [['Visites de la vitrine', $visits], ['Comptes créés', $signups], ['Email vérifié', $verified], ['Onboarding terminé', $onboarded], ['Parcours de 90 jours commencé', $journey], ['Abonnés PRO', $pro]];
        $steps = [];
        foreach ($rows as $i => [$label, $count]) {
            $steps[] = ['label' => $label, 'count' => $count, 'rate' => $i > 0 && $rows[$i - 1][1] > 0 ? round(100 * $count / $rows[$i - 1][1], 1) : null];
        }

        return ['days' => $days, 'steps' => $steps, 'since' => $since, 'visitsSince' => $firstDay];
    }
}
