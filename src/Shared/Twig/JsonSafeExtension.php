<?php

namespace App\Shared\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

/**
 * Filtre `|json_safe` : à utiliser À LA PLACE de `|json_encode|raw` pour passer des données à du JavaScript ou à un attribut.
 * Les caractères qui permettraient de sortir d'une balise `<script>`, d'un attribut ou d'un commentaire HTML (`<`, `>`, `&`, `'`, `"`)
 * sont écrits en séquences \uXXXX à l'intérieur des valeurs : le résultat reste du JSON valide et inoffensif dans le corps
 * d'un script ou dans un attribut entre APOSTROPHES (x-data='{ nom: {{ x|json_safe }} }').
 * Attention : les guillemets qui encadrent un texte JSON ("Esther") restent des guillemets doubles. Dans un attribut entre
 * guillemets doubles, ils fermeraient l'attribut et Alpine afficherait son code brut : écrire l'attribut entre apostrophes.
 * Un texte en UTF-8 invalide est nettoyé (caractère de remplacement) au lieu de faire échouer la page.
 */
final class JsonSafeExtension extends AbstractExtension
{
    public const FLAGS = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR;

    public function getFilters(): array
    {
        return [new TwigFilter('json_safe', self::encode(...), ['is_safe' => ['html']])];
    }

    public static function encode(mixed $value): Markup
    {
        // U+2028 et U+2029 terminent une ligne en JavaScript (anciens moteurs) : on les écrit aussi en \u
        $json = str_replace(["\u{2028}", "\u{2029}"], ['\\u2028', '\\u2029'], json_encode($value, self::FLAGS));

        return new Markup($json, 'UTF-8');
    }
}
