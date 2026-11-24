<?php

namespace App\Academie\Service;

/**
 * Texte des leçons : Markdown rendu côté serveur en mode sûr (Parsedown safe mode : tout HTML brut est échappé,
 * les liens javascript: et data: sont neutralisés). Les liens externes s'ouvrent dans un nouvel onglet.
 */
final class LessonMarkdown
{
    public static function toHtml(?string $markdown): string
    {
        $parser = new \Parsedown();
        $parser->setSafeMode(true)->setBreaksEnabled(true);
        $html = $parser->text((string) $markdown);

        return preg_replace('#<a href="(https?://[^"]+)"#', '<a href="$1" target="_blank" rel="noopener noreferrer"', $html) ?? $html;
    }
}
