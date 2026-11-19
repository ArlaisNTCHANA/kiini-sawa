<?php

namespace App\Shared\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtre `|mermaid_label` : nettoie un texte saisi par l'utilisateur avant de l'écrire dans un libellé de diagramme Mermaid
 * (`Noeud["{{ titre|mermaid_label|escape('html') }}"]`). Retire ce qui permettrait de fermer le libellé ou d'injecter de la syntaxe
 * Mermaid ou du HTML : guillemets, chevrons, accents graves, crochets et accolades, point-virgule, dièse (codes d'entités Mermaid),
 * retours à la ligne et caractères de contrôle. Le texte reste lisible ; les apostrophes sont conservées.
 */
final class MermaidExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('mermaid_label', self::clean(...))];
    }

    public static function clean(mixed $text, int $max = 0): string
    {
        $text = (string) $text;
        $text = preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $text) ?? '';
        $text = str_replace(['"', '“', '”', '<', '>', '`', '[', ']', '{', '}', ';', '\\'], ['\'', '\'', '\'', '', '', '', '(', ')', '(', ')', ',', '/'], $text);
        $text = str_replace('#', '＃', $text);
        $text = trim(preg_replace('/\s{2,}/u', ' ', $text) ?? '');

        return $max > 0 ? mb_substr($text, 0, $max) : $text;
    }
}
