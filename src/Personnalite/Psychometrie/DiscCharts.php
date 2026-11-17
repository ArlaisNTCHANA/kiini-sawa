<?php

namespace App\Personnalite\Psychometrie;

/**
 * Schémas du rapport DISC en SVG (images « data: »), identiques à l'écran et dans le PDF (Dompdf).
 * SVG volontairement simple : formes de base, texte sans effets, pas de marqueurs ni de filtres.
 */
final class DiscCharts
{
    private const FONT = "font-family='DejaVu Sans, Arial, sans-serif'";
    private const LIGHT = ['D' => '#FEE2E2', 'I' => '#FEF3C7', 'S' => '#DCFCE7', 'C' => '#DBEAFE'];

    /** @return array<string, string> URI data des schémas */
    public static function all(array $scores): array
    {
        return [
            'compass' => self::uri(self::compass($scores['natural'], $scores['adapted'])),
            'graphs' => self::uri(self::graphs($scores['natural'], $scores['adapted'])),
            'gauge' => self::uri(self::gauge($scores['gap'])),
            'badge' => self::uri(self::badge($scores['primary'].($scores['secondary'] ?? ''), DiscProfiles::PROFILES[$scores['primary']]['color'])),
        ];
    }

    /** Position sur les deux axes : x = orientation (tâche → relation), y = rythme (posé → rapide), de -1 à 1. */
    public static function position(array $s): array
    {
        $clamp = fn (float $v) => max(-0.9, min(0.9, $v));

        return [$clamp(1.8 * (($s['I'] + $s['S']) - ($s['D'] + $s['C'])) / 100), $clamp(1.8 * (($s['D'] + $s['I']) - ($s['S'] + $s['C'])) / 100)];
    }

    public static function compass(array $natural, array $adapted): string
    {
        $c = DiscProfiles::PROFILES;
        $cx = 210;
        $cy = 200;
        $r = 150;
        $q = [['D', $cx - $r, $cy - $r], ['I', $cx, $cy - $r], ['C', $cx - $r, $cy], ['S', $cx, $cy]];
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='420' height='420' viewBox='0 0 420 420'>";
        foreach ($q as [$l, $x, $y]) {
            $svg .= "<rect x='$x' y='$y' width='$r' height='$r' fill='".self::LIGHT[$l]."' stroke='#FFFFFF' stroke-width='3'/>";
            $tx = 'D' === $l || 'C' === $l ? $x + 16 : $x + $r - 50;
            $ty = 'D' === $l || 'I' === $l ? $y + 44 : $y + $r - 18;
            $svg .= "<text x='$tx' y='$ty' ".self::FONT." font-size='34' font-weight='bold' fill='".$c[$l]['color']."'>$l</text>";
            $ny = 'D' === $l || 'I' === $l ? $ty + 18 : $ty - 40;
            $svg .= "<text x='$tx' y='$ny' ".self::FONT." font-size='11' fill='#475569'>".$c[$l]['name'].'</text>';
        }
        $svg .= "<line x1='$cx' y1='".($cy - $r)."' x2='$cx' y2='".($cy + $r)."' stroke='#94A3B8' stroke-width='1'/>";
        $svg .= "<line x1='".($cx - $r)."' y1='$cy' x2='".($cx + $r)."' y2='$cy' stroke='#94A3B8' stroke-width='1'/>";
        $svg .= "<text x='".($cx - 42)."' y='34' ".self::FONT." font-size='11' fill='#64748B'>Rythme rapide</text>";
        $svg .= "<text x='".($cx - 38)."' y='".($cy + $r + 22)."' ".self::FONT." font-size='11' fill='#64748B'>Rythme posé</text>";
        $svg .= "<text x='".($cx - $r)."' y='".($cy + $r + 40)."' ".self::FONT." font-size='11' fill='#64748B'>Orienté tâche</text>";
        $svg .= "<text x='".($cx + $r - 92)."' y='".($cy + $r + 40)."' ".self::FONT." font-size='11' fill='#64748B'>Orienté relation</text>";

        [$nx, $ny] = self::position($natural);
        [$ax, $ay] = self::position($adapted);
        $p1 = [$cx + $nx * $r, $cy - $ny * $r];
        $p2 = [$cx + $ax * $r, $cy - $ay * $r];
        $dist = hypot($p2[0] - $p1[0], $p2[1] - $p1[1]);
        if ($dist > 24) {
            // Flèche du naturel vers l'adapté, arrêtée au bord du cercle adapté
            $ux = ($p2[0] - $p1[0]) / $dist;
            $uy = ($p2[1] - $p1[1]) / $dist;
            $end = [$p2[0] - $ux * 13, $p2[1] - $uy * 13];
            $svg .= sprintf("<line x1='%.1f' y1='%.1f' x2='%.1f' y2='%.1f' stroke='#334155' stroke-width='2' stroke-dasharray='5,4'/>", $p1[0] + $ux * 11, $p1[1] + $uy * 11, $end[0] - $ux * 6, $end[1] - $uy * 6);
            $svg .= sprintf("<polygon points='%.1f,%.1f %.1f,%.1f %.1f,%.1f' fill='#334155'/>", $end[0], $end[1], $end[0] - $ux * 10 - $uy * 5, $end[1] - $uy * 10 + $ux * 5, $end[0] - $ux * 10 + $uy * 5, $end[1] - $uy * 10 - $ux * 5);
        }
        $svg .= sprintf("<circle cx='%.1f' cy='%.1f' r='12' fill='none' stroke='#0F172A' stroke-width='3'/>", $p2[0], $p2[1]);
        $svg .= sprintf("<circle cx='%.1f' cy='%.1f' r='10' fill='#0F172A'/>", $p1[0], $p1[1]);

        return $svg.'</svg>';
    }

    /** Les deux graphiques classiques : naturel et adapté, avec la ligne moyenne (25 %). */
    public static function graphs(array $natural, array $adapted): string
    {
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='640' height='270' viewBox='0 0 640 270'>";
        foreach ([[0, 'Profil naturel', $natural, false], [330, 'Profil adapté', $adapted, true]] as [$ox, $title, $s, $outline]) {
            $svg .= "<rect x='".($ox + 4)."' y='4' width='302' height='262' rx='14' fill='#F8FAFC' stroke='#E2E8F0'/>";
            $svg .= "<text x='".($ox + 24)."' y='34' ".self::FONT." font-size='15' font-weight='bold' fill='#0F172A'>$title</text>";
            $base = 228;
            $h = 160;
            $mid = $base - $h * 25 / 60;
            $svg .= "<line x1='".($ox + 20)."' y1='$mid' x2='".($ox + 290)."' y2='$mid' stroke='#94A3B8' stroke-width='1' stroke-dasharray='4,4'/>";
            $svg .= "<text x='".($ox + 22)."' y='".($mid - 5)."' ".self::FONT." font-size='9' fill='#94A3B8'>moyenne</text>";
            $svg .= "<line x1='".($ox + 20)."' y1='$base' x2='".($ox + 290)."' y2='$base' stroke='#CBD5E1'/>";
            foreach (DiscBank::LETTERS as $i => $l) {
                $color = DiscProfiles::PROFILES[$l]['color'];
                $bh = max(3, $h * min(60, $s[$l]) / 60);
                $x = $ox + 34 + $i * 66;
                $fill = $outline ? "fill='".self::LIGHT[$l]."' stroke='$color' stroke-width='2'" : "fill='$color'";
                $svg .= sprintf("<rect x='%d' y='%.1f' width='40' height='%.1f' rx='5' %s/>", $x, $base - $bh, $bh, $fill);
                $svg .= sprintf("<text x='%d' y='%.1f' %s font-size='12' font-weight='bold' fill='#334155'>%d%%</text>", $x + ($s[$l] < 10 ? 10 : 6), $base - $bh - 6, self::FONT, $s[$l]);
                $svg .= "<text x='".($x + 13)."' y='".($base + 22)."' ".self::FONT." font-size='15' font-weight='bold' fill='$color'>$l</text>";
            }
        }

        return $svg.'</svg>';
    }

    /** Jauge de l'écart d'adaptation (0 à 50 points). */
    public static function gauge(int $gap): string
    {
        $w = 560;
        $x0 = 20;
        $pos = $x0 + $w * min(50, $gap) / 50;
        $svg = "<svg xmlns='http://www.w3.org/2000/svg' width='600' height='86' viewBox='0 0 600 86'>";
        foreach ([[0, 12, '#22C55E', 'Aligné'], [12, 25, '#F59E0B', 'Ajusté'], [25, 50, '#EF4444', 'Sous tension']] as [$a, $b, $color, $label]) {
            $xa = $x0 + $w * $a / 50;
            $xb = $x0 + $w * $b / 50;
            $svg .= sprintf("<rect x='%.1f' y='30' width='%.1f' height='16' fill='%s' fill-opacity='0.85'/>", $xa, $xb - $xa, $color);
            $svg .= sprintf("<text x='%.1f' y='68' %s font-size='11' fill='#475569'>%s</text>", $xa + 6, self::FONT, $label);
        }
        $svg .= sprintf("<polygon points='%.1f,26 %.1f,12 %.1f,12' fill='#0F172A'/>", $pos, $pos - 8, $pos + 8);
        $svg .= sprintf("<rect x='%.1f' y='26' width='3' height='24' fill='#0F172A'/>", $pos - 1.5);

        return $svg.'</svg>';
    }

    /** Pastille ronde « Vous » avec le code du profil. */
    public static function badge(string $code, string $color): string
    {
        $x = 1 === \strlen($code) ? 47 : 30;

        return "<svg xmlns='http://www.w3.org/2000/svg' width='120' height='120' viewBox='0 0 120 120'><circle cx='60' cy='60' r='58' fill='$color'/>"
            ."<text x='$x' y='68' ".self::FONT." font-size='32' font-weight='bold' fill='#FFFFFF'>$code</text>"
            ."<text x='44' y='90' ".self::FONT." font-size='12' fill='#FFFFFF'>Vous</text></svg>";
    }

    private static function uri(string $svg): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
