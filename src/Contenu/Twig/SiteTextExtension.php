<?php

namespace App\Contenu\Twig;

use App\Contenu\Service\SiteTexts;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

/**
 * {{ t('vitrine.hero.titre', 'Texte d\'origine') }} : un texte court modifiable dans l'administration.
 * {{ t('code', 'origine', 'inline') }} accepte *italique*, **gras**, [lien](/url) ; 'markdown' accepte aussi titres, listes et paragraphes.
 * {{ doc('legal.cgu') }} : un document long dont le texte d'origine est le fichier content/textes/legal/cgu.md.
 * Variables : t('code', 'Bonjour {nom}', 'text', {nom: user.name}). Le HTML saisi est toujours neutralisé.
 */
final class SiteTextExtension extends AbstractExtension
{
    public function __construct(private readonly SiteTexts $texts)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('t', $this->text(...)),
            new TwigFunction('doc', $this->document(...), ['is_safe' => ['html']]),
        ];
    }

    /** @param array<string, scalar> $vars */
    public function text(string $code, string $default, string $format = 'text', array $vars = []): string|Markup
    {
        $value = self::fill($this->texts->get($code, $default), $vars);

        return match ($format) {
            'inline' => new Markup(self::markdown($value, true), 'UTF-8'),
            'markdown' => new Markup(self::markdown($value, false), 'UTF-8'),
            default => $value,
        };
    }

    /**
     * Remplace les {variables}, avec l'élision du français devant une valeur qui commence par une voyelle ou un h :
     * « que {prenom} » donne « qu'Arlais », « de {prenom} » donne « d'Arlais » (et « de Marie » reste « de Marie »).
     *
     * @param array<string, scalar> $vars
     */
    public static function fill(string $text, array $vars): string
    {
        foreach ($vars as $k => $v) {
            $v = (string) $v;
            if (1 === preg_match('/^[AEIOUÀÂÄÉÈÊËÎÏÔÖÙÛÜHaeiouàâäéèêëîïôöùûüh]/u', $v)) {
                $text = preg_replace_callback('/\b(que|Que|de|De|jusque|lorsque|puisque) \{'.preg_quote((string) $k, '/').'\}/u',
                    fn (array $m) => mb_substr($m[1], 0, -1).'\''.$v, $text) ?? $text;
            }
            $text = str_replace('{'.$k.'}', $v, $text);
        }

        return $text;
    }

    public function document(string $code): string
    {
        return self::markdown($this->texts->document($code), false);
    }

    /** Markdown sans HTML brut (mode sûr) ; les passages « [À COMPLÉTER…] » restent surlignés. */
    public static function markdown(string $text, bool $inline): string
    {
        $parser = new \Parsedown();
        $parser->setSafeMode(true)->setBreaksEnabled(true);
        $html = $inline ? $parser->line($text) : $parser->text($text);

        return preg_replace('/\[À COMPLÉTER[^\]]*\]/u', '<span class="todo">$0</span>', $html) ?? $html;
    }
}
