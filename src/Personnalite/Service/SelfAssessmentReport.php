<?php

namespace App\Personnalite\Service;

use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\ReportCharts;

/**
 * Prépare les rapports illustrés des auto-évaluations « Équilibre de Vie » et « Hygiène de Vie ».
 */
final class SelfAssessmentReport
{
    public const TEMPLATES = [
        'EQUILIBRE' => 'personnalite/assessment/report/self.html.twig',
        'HYGIENE' => 'personnalite/assessment/report/self.html.twig',
    ];

    public function build(ProfileTest $test): ?array
    {
        $type = strtoupper($test->getType());
        $data = json_decode($test->getResult(), true);
        if (!isset(self::TEMPLATES[$type]) || !\is_array($data)) {
            return null;
        }
        $base = ['template' => self::TEMPLATES[$type], 'type' => $type, 'test' => $test, 'assessment' => ['takenAt' => $test->getDate()], 'quality' => []];

        return $base + ('EQUILIBRE' === $type ? $this->equilibre($data) : $this->hygiene($data));
    }

    private function areas(array $scores, array $profiles): array
    {
        $areas = [];
        foreach ($profiles as $key => $p) {
            $v = (float) ($scores[$key] ?? 5);
            $level = SelfAssessmentProfiles::level($v);
            $areas[$key] = $p + ['key' => $key, 'value' => $v, 'level' => $level, 'levelLabel' => SelfAssessmentProfiles::levelLabel($level)];
        }

        return $areas;
    }

    private function summary(array $areas): array
    {
        $sorted = $areas;
        uasort($sorted, fn ($a, $b) => $a['value'] <=> $b['value']);
        $avg = array_sum(array_column($areas, 'value')) / max(1, \count($areas));

        return [
            'avg' => round($avg, 1),
            'priorities' => \array_slice($sorted, 0, 3, true),
            'supports' => \array_slice(array_reverse($sorted, true), 0, 2, true),
            'bars' => ReportCharts::uri(ReportCharts::hbars(array_map(fn ($a) => ['label' => $a['label'], 'value' => $a['value'] * 10, 'color' => $a['color'], 'strong' => 'low' === $a['level']], array_values($sorted)), 200)),
            'gauge' => ReportCharts::uri(ReportCharts::levelGauge($avg * 10, $avg < 5 ? '#DC2626' : ($avg < 7.5 ? '#D97706' : '#16A34A'))),
            'globalLevel' => SelfAssessmentProfiles::level($avg),
        ];
    }

    private function equilibre(array $scores): array
    {
        $areas = $this->areas($scores, SelfAssessmentProfiles::EQUILIBRE);
        foreach ($areas as $k => $a) {
            [$areas[$k]['text'], $areas[$k]['actions']] = $a[$a['level']];
        }

        return ['areas' => $areas, 'summary' => $this->summary($areas),
            'wheel' => ReportCharts::uri(ReportCharts::wheel(array_map(fn ($a) => ['label' => $a['label'], 'value' => $a['value'], 'color' => $a['color']], array_values($areas))))];
    }

    private function hygiene(array $data): array
    {
        $areas = $this->areas($data['axes'] ?? [], SelfAssessmentProfiles::HYGIENE);
        foreach ($areas as $k => $a) {
            $areas[$k]['text'] = $a[$a['level']];
            $subs = $data['details'][$k] ?? [];
            $areas[$k]['subs'] = $subs;
            $areas[$k]['subChart'] = $subs ? ReportCharts::uri(ReportCharts::hbars(array_map(fn ($label, $v) => ['label' => $label, 'value' => $v * 10, 'color' => $a['color'], 'strong' => $v < 5], array_keys($subs), array_values($subs)), 200)) : null;
        }

        return ['areas' => $areas, 'summary' => $this->summary($areas),
            'wheel' => ReportCharts::uri(ReportCharts::wheel(array_map(fn ($a) => ['label' => $a['label'], 'value' => $a['value'], 'color' => $a['color']], array_values($areas))))];
    }
}
