<?php

namespace App\Media\Service;

use Doctrine\DBAL\Connection;

/**
 * Rétention : part des inscrits revenus au moins une fois 7, 30 et 90 jours après leur inscription
 * (date de dernière activité ≥ inscription + N jours), par semaine d'inscription et par canal.
 * Un taux n'est calculé que pour les inscrits assez anciens (sinon « — »). Comptes vérifiés, administrateurs exclus.
 */
final class RetentionReport
{
    public const HORIZONS = [7, 30, 90];

    public function __construct(private readonly Connection $db)
    {
    }

    /** @return array{verified: int, active7: int, active30: int} */
    public function headline(\DateTimeImmutable $today): array
    {
        $row = $this->db->fetchAssociative(
            "SELECT COUNT(*) AS verified,
                    SUM(CASE WHEN u.last_active_at >= :d7 THEN 1 ELSE 0 END) AS active7,
                    SUM(CASE WHEN u.last_active_at >= :d30 THEN 1 ELSE 0 END) AS active30
             FROM `user` u WHERE u.is_verified = 1 AND u.roles NOT LIKE '%ROLE_ADMIN%'",
            ['d7' => $today->modify('-7 days')->format('Y-m-d H:i:s'), 'd30' => $today->modify('-30 days')->format('Y-m-d H:i:s')]
        );

        return ['verified' => (int) $row['verified'], 'active7' => (int) $row['active7'], 'active30' => (int) $row['active30']];
    }

    /**
     * @return list<array{label: string, signups: int, rates: array<int, ?float>}> semaines les plus récentes d'abord
     */
    public function byWeek(\DateTimeImmutable $today, int $weeks = 12): array
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT DATE_SUB(DATE(u.created_at), INTERVAL WEEKDAY(u.created_at) DAY) AS week_start, ".$this->columns()."
             FROM `user` u
             WHERE u.is_verified = 1 AND u.roles NOT LIKE '%ROLE_ADMIN%' AND u.created_at >= :since
             GROUP BY week_start ORDER BY week_start DESC",
            $this->params($today) + ['since' => $today->modify('-'.$weeks.' weeks')->format('Y-m-d')]
        );

        return array_map(fn (array $r) => ['label' => 'Semaine du '.(new \DateTimeImmutable($r['week_start']))->format('d/m'), 'signups' => (int) $r['signups'], 'rates' => $this->rates($r)], $rows);
    }

    /**
     * @return list<array{label: string, signups: int, rates: array<int, ?float>}> canaux par nombre d'inscrits
     */
    public function byChannel(\DateTimeImmutable $today, int $days = 365): array
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT COALESCE(o.source, '(avant le suivi)') AS label, ".$this->columns()."
             FROM `user` u LEFT JOIN signup_origin o ON o.user_id = u.id
             WHERE u.is_verified = 1 AND u.roles NOT LIKE '%ROLE_ADMIN%' AND u.created_at >= :since
             GROUP BY label ORDER BY signups DESC",
            $this->params($today) + ['since' => $today->modify('-'.$days.' days')->format('Y-m-d')]
        );

        return array_map(fn (array $r) => ['label' => (string) $r['label'], 'signups' => (int) $r['signups'], 'rates' => $this->rates($r)], $rows);
    }

    private function columns(): string
    {
        $cols = ['COUNT(*) AS signups'];
        foreach (self::HORIZONS as $n) {
            // Éligibles : inscrits depuis au moins N jours ; revenus : dernière activité au moins N jours après l'inscription
            $cols[] = "SUM(CASE WHEN u.created_at <= :cut$n THEN 1 ELSE 0 END) AS eligible$n";
            $cols[] = "SUM(CASE WHEN u.created_at <= :cut$n AND u.last_active_at IS NOT NULL AND DATEDIFF(u.last_active_at, u.created_at) >= $n THEN 1 ELSE 0 END) AS back$n";
        }

        return implode(', ', $cols);
    }

    /** @return array<string, string> */
    private function params(\DateTimeImmutable $today): array
    {
        $p = [];
        foreach (self::HORIZONS as $n) {
            $p['cut'.$n] = $today->modify('-'.$n.' days')->format('Y-m-d 23:59:59');
        }

        return $p;
    }

    /** @return array<int, ?float> */
    private function rates(array $r): array
    {
        $rates = [];
        foreach (self::HORIZONS as $n) {
            $eligible = (int) $r['eligible'.$n];
            $rates[$n] = $eligible > 0 ? round(100 * (int) $r['back'.$n] / $eligible) : null;
        }

        return $rates;
    }
}
