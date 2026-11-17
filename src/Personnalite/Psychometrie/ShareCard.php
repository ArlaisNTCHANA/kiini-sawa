<?php

namespace App\Personnalite\Psychometrie;

/**
 * Résumé partageable d'un rapport : une accroche, un résultat principal, une phrase. Rien d'autre (pas de scores
 * détaillés, pas de points de vigilance) : c'est ce que l'utilisateur choisit de montrer.
 */
final class ShareCard
{
    /**
     * @param array<string, mixed> $report sortie de ReportBuilder::build()
     *
     * @return array{kicker: string, title: string, desc: string, color: string, testName: string, slug: string}
     */
    public static function from(array $report): array
    {
        $def = $report['instrument'];
        $base = ['color' => $def['color'], 'testName' => $def['name'], 'slug' => $report['slug']];

        if (!empty($report['enneagram'])) {
            $e = $report['enneagram'];

            return $base + ['kicker' => 'Ma motivation profonde', 'title' => 'Type '.$e['type'].' · '.$e['profile']['name'], 'desc' => $e['profile']['motivation']];
        }
        if (!empty($report['gifts'])) {
            $top = \array_slice($report['gifts']['top'], 0, 3);

            return $base + ['kicker' => 'Mes dons spirituels', 'title' => implode(' · ', array_column($top, 'name')), 'desc' => $top[0]['desc'] ?? ''];
        }
        if (!empty($report['disc'])) {
            $d = $report['disc'];

            return $base + ['kicker' => 'Mon profil DISC', 'title' => $d['combo']['name'] ?? $d['profile']['name'],
                'desc' => ($d['combo'] ? $d['profile']['name'].' – '.$d['secondaryProfile']['name'].'. '.$d['combo']['desc'] : $d['profile']['essence'])];
        }
        if (!empty($report['style']) && 'type' !== $report['slug']) {
            $s = $report['style']['profile'];

            return $base + ['kicker' => 'Mon style comportemental', 'title' => $s['name'], 'desc' => $s['essence'].' '.$s['strengths']];
        }
        if (!empty($report['type'])) {
            return $base + ['kicker' => 'Mon type de fonctionnement', 'title' => $report['type']['name'], 'desc' => $report['type']['desc']];
        }

        return $base + ['kicker' => 'Ma personnalité', 'title' => 'Mes 5 grandes dimensions', 'desc' => 'J\'ai découvert mes forces avec un questionnaire issu de la recherche.'];
    }
}
