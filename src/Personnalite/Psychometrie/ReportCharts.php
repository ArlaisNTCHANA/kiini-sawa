<?php

namespace App\Personnalite\Psychometrie;

/**
 * Schémas SVG des rapports (ennéagramme, Big Five, type, dons), en images « data: » compatibles Dompdf :
 * formes de base, texte positionné à la main (pas de text-anchor), pas de marqueurs ni de filtres.
 */
final class ReportCharts
{
    private const FONT = "font-family='DejaVu Sans, Arial, sans-serif'";

    public static function uri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /** Largeur approximative d'un texte (pour centrer sans text-anchor). */
    private static function tw(string $text, int $size): float
    {
        return mb_strlen($text) * $size * 0.6;
    }

    private static function text(float $x, float $y, string $t, int $size, string $fill, bool $bold = false, bool $center = false): string
    {
        $x = $center ? $x - self::tw($t, $size) / 2 : $x;

        return sprintf("<text x='%.1f' y='%.1f' %s font-size='%d'%s fill='%s'>%s</text>", $x, $y, self::FONT, $size, $bold ? " font-weight='bold'" : '', $fill, htmlspecialchars($t, \ENT_XML1));
    }

    private static function arrow(array $a, array $b, string $color, float $shorten, bool $dashed): string
    {
        $d = hypot($b[0] - $a[0], $b[1] - $a[1]) ?: 1;
        $ux = ($b[0] - $a[0]) / $d;
        $uy = ($b[1] - $a[1]) / $d;
        $s = [$a[0] + $ux * $shorten, $a[1] + $uy * $shorten];
        $e = [$b[0] - $ux * $shorten, $b[1] - $uy * $shorten];
        $svg = sprintf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' stroke='%s' stroke-width='3'%s/>", $s[0], $s[1], $e[0] - $ux * 8, $e[1] - $uy * 8, $color, $dashed ? " stroke-dasharray='7,5'" : '');

        return $svg.sprintf("<polygon points='%.1f,%.1f %.1f,%.1f %.1f,%.1f' fill='%s'/>", $e[0], $e[1], $e[0] - $ux * 13 - $uy * 7, $e[1] - $uy * 13 + $ux * 7, $e[0] - $ux * 13 + $uy * 7, $e[1] - $uy * 13 - $ux * 7, $color);
    }

    // ── Ennéagramme ────────────────────────────────────────────────

    public static function enneagram(int $type, int $wing): string
    {
        $cx = 230;
        $cy = 225;
        $r = 165;
        $pt = fn (int $k) => [$cx + $r * cos(deg2rad(-90 + $k * 40)), $cy + $r * sin(deg2rad(-90 + $k * 40))];
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='460' height='470' viewBox='0 0 460 470'>";
        $svg .= "<circle cx='$cx' cy='$cy' r='$r' fill='none' stroke='#CBD5E1' stroke-width='2'/>";
        foreach ([[1, 4, 2, 8, 5, 7, 1], [3, 6, 9, 3]] as $path) {
            for ($i = 0; $i < \count($path) - 1; ++$i) {
                [$x1, $y1] = $pt($path[$i]);
                [$x2, $y2] = $pt($path[$i + 1]);
                $svg .= sprintf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' stroke='#E2E8F0' stroke-width='1.5'/>", $x1, $y1, $x2, $y2);
            }
        }
        [$grow, $stress] = EnneagramProfiles::ARROWS[$type];
        $svg .= self::arrow($pt($type), $pt($grow), '#16A34A', 26, false);
        $svg .= self::arrow($pt($type), $pt($stress), '#DC2626', 26, true);
        foreach (range(1, 9) as $k) {
            [$x, $y] = $pt($k);
            $center = EnneagramProfiles::CENTERS[EnneagramProfiles::centerOf($k)];
            if ($k === $type) {
                $svg .= sprintf("<circle cx='%.1f' cy='%.1f' r='26' fill='%s'/>", $x, $y, $center['color']).self::text($x, $y + 8, (string) $k, 22, '#FFFFFF', true, true);
            } elseif ($k === $wing) {
                $svg .= sprintf("<circle cx='%.1f' cy='%.1f' r='19' fill='%s' stroke='%s' stroke-width='3'/>", $x, $y, $center['light'], $center['color']).self::text($x, $y + 6, (string) $k, 16, $center['dark'], true, true);
            } else {
                $svg .= sprintf("<circle cx='%.1f' cy='%.1f' r='16' fill='%s'/>", $x, $y, $center['light']).self::text($x, $y + 5, (string) $k, 14, $center['dark'], false, true);
            }
        }

        return $svg.'</svg>';
    }

    /**
     * Barres horizontales classées.
     *
     * @param list<array{label: string, value: float, color: string, strong?: bool}> $rows valeurs de 0 à 100
     */
    public static function hbars(array $rows, int $labelWidth = 190): string
    {
        $h = 30 * \count($rows) + 12;
        $w = 640;
        $barW = $w - $labelWidth - 60;
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='$w' height='$h' viewBox='0 0 $w $h'>";
        foreach ($rows as $i => $row) {
            $y = 8 + $i * 30;
            $strong = $row['strong'] ?? false;
            $svg .= self::text(0, $y + 15, $row['label'], 12, $strong ? '#0F172A' : '#475569', $strong);
            $svg .= "<rect x='$labelWidth' y='".($y + 3)."' width='$barW' height='16' rx='8' fill='#F1F5F9'/>";
            $bw = max(4, $barW * min(100, $row['value']) / 100);
            $svg .= sprintf("<rect x='%d' y='%d' width='%.1f' height='16' rx='8' fill='%s'%s/>", $labelWidth, $y + 3, $bw, $row['color'], $strong ? '' : " fill-opacity='0.45'");
            $svg .= self::text($labelWidth + $barW + 10, $y + 16, (string) round($row['value']), 12, '#334155', $strong);
        }

        return $svg.'</svg>';
    }

    // ── Convergence des tests ──────────────────────────────────────

    /**
     * Trois axes bipolaires ; chaque test place un repère, le cercle noir montre la moyenne.
     *
     * @param list<array{left: string, right: string, markers: list<array{name: string, value: float, color: string}>, mean: ?float}> $axes valeurs de -1 à 1
     */
    public static function convergence(array $axes): string
    {
        $h = 96 * \count($axes) + 10;
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='640' height='$h' viewBox='0 0 640 $h'>";
        $x0 = 60;
        $w = 520;
        foreach ($axes as $i => $a) {
            $y = 30 + $i * 96;
            $svg .= self::text(0, $y - 8, $a['left'], 12, '#475569', true);
            $svg .= self::text(640 - self::tw($a['right'], 12), $y - 8, $a['right'], 12, '#475569', true);
            $svg .= "<rect x='$x0' y='".($y + 8)."' width='$w' height='8' rx='4' fill='#E2E8F0'/>";
            $svg .= "<line x1='".($x0 + $w / 2)."' y1='".($y + 2)."' x2='".($x0 + $w / 2)."' y2='".($y + 22)."' stroke='#94A3B8' stroke-width='2'/>";
            foreach ($a['markers'] as $j => $m) {
                $mx = $x0 + $w * ($m['value'] + 1) / 2;
                $svg .= sprintf("<circle cx='%.1f' cy='%d' r='8' fill='%s' stroke='#FFFFFF' stroke-width='2'/>", $mx, $y + 12, $m['color']);
                $svg .= self::text($mx, $y + 40 + ($j % 2) * 13, $m['name'], 10, $m['color'], true, true);
            }
            if (null !== $a['mean']) {
                $mx = $x0 + $w * ($a['mean'] + 1) / 2;
                $svg .= sprintf("<circle cx='%.1f' cy='%d' r='12' fill='none' stroke='#0F172A' stroke-width='3'/>", $mx, $y + 12);
            }
        }

        return $svg.'</svg>';
    }

    // ── Roue de la vie ─────────────────────────────────────────────

    /** @param list<array{label: string, value: float, color: string}> $segments valeurs sur 10 */
    public static function wheel(array $segments): string
    {
        $cx = 300;
        $cy = 250;
        $r = 165;
        $n = \count($segments);
        $step = 360 / $n;
        $sector = function (float $from, float $to, float $radius) use ($cx, $cy): string {
            $pts = ["$cx,$cy"];
            for ($a = $from; $a <= $to + 0.01; $a += ($to - $from) / 16) {
                $pts[] = sprintf('%.1f,%.1f', $cx + $radius * cos(deg2rad($a)), $cy + $radius * sin(deg2rad($a)));
            }

            return implode(' ', $pts);
        };
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='500' viewBox='0 0 600 500'>";
        foreach ($segments as $i => $seg) {
            $from = -90 + $i * $step;
            $to = $from + $step;
            $svg .= "<polygon points='".$sector($from, $to, $r)."' fill='#F8FAFC' stroke='#FFFFFF' stroke-width='3'/>";
            $svg .= "<polygon points='".$sector($from, $to, max(6, $r * min(10, $seg['value']) / 10))."' fill='{$seg['color']}' fill-opacity='0.85' stroke='#FFFFFF' stroke-width='3'/>";
        }
        foreach ([2.5, 5, 7.5, 10] as $lvl) {
            $svg .= sprintf("<circle cx='%d' cy='%d' r='%.1f' fill='none' stroke='#CBD5E1' stroke-width='%s' stroke-dasharray='%s'/>", $cx, $cy, $r * $lvl / 10, 5 == $lvl ? '1.5' : '0.8', 10 == $lvl ? 'none' : '3,4');
        }
        foreach ($segments as $i => $seg) {
            $mid = deg2rad(-90 + ($i + 0.5) * $step);
            $lx = $cx + ($r + 38) * cos($mid);
            $ly = $cy + ($r + 30) * sin($mid);
            $svg .= self::text($lx, $ly, $seg['label'], 12, '#0F172A', true, true);
            $svg .= self::text($lx, $ly + 16, str_replace('.', ',', (string) round($seg['value'], 1)).' / 10', 11, $seg['color'], true, true);
        }

        return $svg.'</svg>';
    }

    // ── Big Five ───────────────────────────────────────────────────

    /** @param array<string, array{label: string, value: float}> $axes 5 dimensions, valeurs 0-100 */
    public static function radar(array $axes, string $color): string
    {
        $cx = 300;
        $cy = 215;
        $r = 150;
        $n = \count($axes);
        $keys = array_keys($axes);
        $pt = fn (int $i, float $v) => [$cx + $r * $v / 100 * cos(deg2rad(-90 + $i * 360 / $n)), $cy + $r * $v / 100 * sin(deg2rad(-90 + $i * 360 / $n))];
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='430' viewBox='0 0 600 430'>";
        foreach ([25, 50, 75, 100] as $lvl) {
            $pts = implode(' ', array_map(fn ($i) => vsprintf('%.1f,%.1f', $pt($i, $lvl)), range(0, $n - 1)));
            $svg .= "<polygon points='$pts' fill='".(50 === $lvl ? '#F8FAFC' : 'none')."' stroke='#E2E8F0' stroke-width='".(50 === $lvl ? 2 : 1)."'/>";
        }
        foreach (range(0, $n - 1) as $i) {
            [$x, $y] = $pt($i, 100);
            $svg .= sprintf("<line x1='%d' y1='%d' x2='%.1f' y2='%.1f' stroke='#E2E8F0'/>", $cx, $cy, $x, $y);
        }
        $pts = implode(' ', array_map(fn ($i) => vsprintf('%.1f,%.1f', $pt($i, max(4, $axes[$keys[$i]]['value']))), range(0, $n - 1)));
        $svg .= "<polygon points='$pts' fill='$color' fill-opacity='0.25' stroke='$color' stroke-width='3'/>";
        foreach (range(0, $n - 1) as $i) {
            [$x, $y] = $pt($i, max(4, $axes[$keys[$i]]['value']));
            $svg .= sprintf("<circle cx='%.1f' cy='%.1f' r='6' fill='%s'/>", $x, $y, $color);
            [$lx, $ly] = $pt($i, 122);
            $label = $axes[$keys[$i]]['label'];
            $svg .= self::text($lx, $ly, $label, 13, '#0F172A', true, true);
            $svg .= self::text($lx, $ly + 16, (string) round($axes[$keys[$i]]['value']), 12, '#64748B', false, true);
        }

        return $svg.'</svg>';
    }

    /** Jauge 0-100 avec trois zones (bas, moyen, haut). */
    public static function levelGauge(float $value, string $color): string
    {
        $w = 520;
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='560' height='54' viewBox='0 0 560 54'>";
        foreach ([[0, 35, '#F1F5F9', 'Plutôt bas'], [35, 65, '#E2E8F0', 'Moyen'], [65, 100, '#F1F5F9', 'Plutôt haut']] as [$a, $b, $fill, $label]) {
            $svg .= sprintf("<rect x='%.1f' y='14' width='%.1f' height='14' fill='%s'/>", 20 + $w * $a / 100, $w * ($b - $a) / 100, $fill);
            $svg .= self::text(20 + $w * ($a + $b) / 200, 46, $label, 10, '#94A3B8', false, true);
        }
        $svg .= sprintf("<rect x='20' y='14' width='%.1f' height='14' fill='%s' fill-opacity='0.85'/>", $w * max(2, $value) / 100, $color);
        $x = 20 + $w * max(2, $value) / 100;
        $svg .= sprintf("<circle cx='%.1f' cy='21' r='9' fill='%s' stroke='#FFFFFF' stroke-width='3'/>", $x, $color);

        return $svg.'</svg>';
    }

    // ── Type de fonctionnement ─────────────────────────────────────

    /** @param list<array{left: string, right: string, leftName: string, rightName: string, letter: string, clarity: int}> $axes */
    public static function typeAxes(array $axes, string $color): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='640' height='".(70 * \count($axes) + 10)."' viewBox='0 0 640 ".(70 * \count($axes) + 10)."'>";
        foreach ($axes as $i => $a) {
            $y = 12 + $i * 70;
            $mid = 320;
            $half = 190;
            $leftOn = $a['letter'] === $a['left'];
            $svg .= self::text(10, $y + 26, $a['left'], 22, $leftOn ? $color : '#CBD5E1', true);
            $svg .= self::text(10, $y + 44, $a['leftName'], 11, $leftOn ? '#0F172A' : '#94A3B8', $leftOn);
            $svg .= self::text(600, $y + 26, $a['right'], 22, $leftOn ? '#CBD5E1' : $color, true);
            $svg .= self::text(630 - self::tw($a['rightName'], 11), $y + 44, $a['rightName'], 11, $leftOn ? '#94A3B8' : '#0F172A', !$leftOn);
            $svg .= "<rect x='".($mid - $half)."' y='".($y + 14)."' width='".(2 * $half)."' height='14' rx='7' fill='#F1F5F9'/>";
            $len = max(6, $half * min(100, $a['clarity']) / 100);
            $x = $leftOn ? $mid - $len : $mid;
            $svg .= sprintf("<rect x='%.1f' y='%d' width='%.1f' height='14' rx='7' fill='%s'/>", $x, $y + 14, $len, $color);
            $svg .= "<line x1='$mid' y1='".($y + 8)."' x2='$mid' y2='".($y + 34)."' stroke='#94A3B8' stroke-width='2'/>";
            $svg .= self::text($mid, $y + 52, 'netteté '.$a['clarity'].'/100', 10, '#64748B', false, true);
        }

        return $svg.'</svg>';
    }

    /** Grille des 16 types, le vôtre mis en avant. */
    public static function typeGrid(string $code, string $color, array $names): string
    {
        $rows = [['ISTJ', 'ISFJ', 'INFJ', 'INTJ'], ['ISTP', 'ISFP', 'INFP', 'INTP'], ['ESTP', 'ESFP', 'ENFP', 'ENTP'], ['ESTJ', 'ESFJ', 'ENFJ', 'ENTJ']];
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='640' height='300' viewBox='0 0 640 300'>";
        foreach ($rows as $r => $row) {
            foreach ($row as $c => $t) {
                $x = 4 + $c * 158;
                $y = 4 + $r * 74;
                $on = $t === $code;
                $svg .= "<rect x='$x' y='$y' width='150' height='66' rx='12' fill='".($on ? $color : '#F8FAFC')."' stroke='".($on ? $color : '#E2E8F0')."'/>";
                $svg .= self::text($x + 75, $y + 30, $t, 17, $on ? '#FFFFFF' : '#334155', true, true);
                $svg .= self::text($x + 75, $y + 50, $names[$t] ?? '', 10, $on ? '#FFFFFF' : '#94A3B8', $on, true);
            }
        }

        return $svg.'</svg>';
    }
}
