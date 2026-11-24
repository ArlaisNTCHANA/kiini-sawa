<?php

namespace App\Academie\Service;

/**
 * Texte des leçons : Markdown rendu côté serveur en mode sûr (Parsedown safe mode : tout HTML brut est échappé,
 * les liens javascript: et data: sont neutralisés). Les liens externes s'ouvrent dans un nouvel onglet.
 *
 * Trois ajouts au Markdown, sans HTML brut :
 *
 * - **Encadrés** : une ligne `:::retenir` (ou `:::exemple`, `:::avous`, `:::attention`), éventuellement suivie d'un
 *   titre, le texte de l'encadré, puis une ligne `:::` ;
 * - **Schémas dessinés** : une ligne `[[schema:code]]` (liste dans LessonDiagrams::CATALOG) ;
 * - **Images** : `![description](/academie/images/…)`, seulement les images envoyées dans le studio
 *   (AcademyImages) ; toute autre image est retirée.
 */
final class LessonMarkdown
{
    /** Type d'encadré => [icône Lucide, titre par défaut]. */
    public const CALLOUTS = [
        'retenir' => ['lightbulb', 'À retenir'],
        'exemple' => ['sparkles', 'Exemple'],
        'avous' => ['pencil-line', 'À vous'],
        'attention' => ['triangle-alert', 'Attention'],
    ];

    /**
     * @param (callable(string): ?string)|null $diagram   rend un schéma (code => HTML) ; sans lui, les schémas sont ignorés
     * @param string                            $imageBase préfixe des adresses d'images (adresse du site pour l'application mobile)
     */
    public static function toHtml(?string $markdown, ?callable $diagram = null, string $imageBase = ''): string
    {
        $blocks = [];
        $source = self::extractBlocks((string) $markdown, $blocks, $diagram, $imageBase);
        $html = self::parse($source, $imageBase);
        foreach ($blocks as $token => $blockHtml) {
            $html = str_replace(['<p>'.$token.'</p>', $token], $blockHtml, $html);
        }

        return $html;
    }

    private static function parse(string $markdown, string $imageBase): string
    {
        $parser = new \Parsedown();
        $parser->setSafeMode(true)->setBreaksEnabled(true);
        $html = $parser->text($markdown);
        $html = preg_replace('#<a href="(https?://[^"]+)"#', '<a href="$1" target="_blank" rel="noopener noreferrer"', $html) ?? $html;

        // Images : seulement celles du studio (adresse exacte), chargées à la demande ; les autres disparaissent
        return preg_replace_callback('#<img src="([^"]*)" alt="([^"]*)"\s*/?>#', static function (array $m) use ($imageBase): string {
            if (!AcademyImages::isPublicPath($m[1])) {
                return '';
            }

            return '<img src="'.htmlspecialchars($imageBase, \ENT_QUOTES).$m[1].'" alt="'.$m[2].'" loading="lazy" class="academie-image">';
        }, $html) ?? $html;
    }

    /** Remplace encadrés et schémas par des jetons, rendus à part (le contenu d'un encadré reste du Markdown sûr). */
    private static function extractBlocks(string $markdown, array &$blocks, ?callable $diagram, string $imageBase): string
    {
        $lines = preg_split('/\R/', $markdown) ?: [];
        $out = [];
        $open = null;
        $buffer = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (null === $open && preg_match('/^:::(\w+)\s*(.*)$/u', $trimmed, $m) && isset(self::CALLOUTS[strtolower($m[1])])) {
                $open = [strtolower($m[1]), trim($m[2])];
                $buffer = [];
                continue;
            }
            if (null !== $open) {
                if (':::' === $trimmed) {
                    [$type, $title] = $open;
                    [$icon, $default] = self::CALLOUTS[$type];
                    $token = self::token($blocks);
                    $blocks[$token] = '<aside class="academie-encadre academie-encadre--'.$type.'"><p class="academie-encadre__titre"><i data-lucide="'.$icon.'" aria-hidden="true"></i> '
                        .htmlspecialchars('' !== $title ? $title : $default, \ENT_QUOTES).'</p>'.self::parse(implode("\n", $buffer), $imageBase).'</aside>';
                    $out[] = '';
                    $out[] = $token;
                    $out[] = '';
                    $open = null;
                } else {
                    $buffer[] = $line;
                }
                continue;
            }
            if (preg_match('/^\[\[schema:([a-z0-9-]+)\]\]$/', $trimmed, $m)) {
                $rendered = $diagram ? $diagram($m[1]) : null;
                if (null !== $rendered) {
                    $token = self::token($blocks);
                    $blocks[$token] = $rendered;
                    $out[] = '';
                    $out[] = $token;
                    $out[] = '';
                }
                continue;
            }
            $out[] = $line;
        }
        // Encadré jamais fermé : son texte reste affiché normalement
        if (null !== $open) {
            array_push($out, ...$buffer);
        }

        return implode("\n", $out);
    }

    private static function token(array $blocks): string
    {
        return 'KSBLOC'.\count($blocks).'X'.bin2hex(random_bytes(4));
    }
}
