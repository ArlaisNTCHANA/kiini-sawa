<?php

namespace App\Academie\Service;

/**
 * Éditeur de leçon par blocs : passage du texte d'une leçon (Markdown, tel qu'il est enregistré) à une liste de blocs
 * modifiables un par un, et retour. Le texte enregistré ne change pas de format : pages des membres, application mobile
 * et chargement de fichiers continuent de fonctionner sans rien savoir des blocs.
 *
 * Blocs : paragraphe, titre (niveau 2 ou 3), liste (à puces ou numérotée, un point par ligne), citation (une ligne par
 * ligne), encadré (LessonMarkdown::CALLOUTS), schéma (code), image (du studio) et « avancé » (Markdown libre : tableaux…).
 * Tout ce qui n'est pas reconnu devient un bloc avancé : rien ne se perd.
 */
final class LessonBlocks
{
    public const TYPES = ['paragraphe', 'titre', 'liste', 'citation', 'encadre', 'schema', 'image', 'avance'];
    public const MAX_BLOCKS = 200;
    public const MAX_TEXT = 20000;

    /** @return list<array<string, mixed>> */
    public static function fromMarkdown(string $markdown): array
    {
        $lines = preg_split('/\R/', str_replace("\r\n", "\n", $markdown)) ?: [];
        $blocks = [];
        $n = \count($lines);
        for ($i = 0; $i < $n;) {
            $line = $lines[$i];
            $trim = trim($line);
            if ('' === $trim) {
                ++$i;
                continue;
            }
            // Encadré :::type titre … :::
            if (preg_match('/^:::(\w+)\s*(.*)$/u', $trim, $m) && isset(LessonMarkdown::CALLOUTS[strtolower($m[1])])) {
                $inner = [];
                $j = $i + 1;
                while ($j < $n && ':::' !== trim($lines[$j])) {
                    $inner[] = $lines[$j];
                    ++$j;
                }
                if ($j < $n) {
                    $blocks[] = ['type' => 'encadre', 'kind' => strtolower($m[1]), 'title' => trim($m[2]), 'text' => trim(implode("\n", $inner))];
                    $i = $j + 1;
                    continue;
                }
            }
            if (preg_match('/^\[\[schema:([a-z0-9-]+)\]\]$/', $trim, $m)) {
                $blocks[] = ['type' => 'schema', 'code' => $m[1]];
                ++$i;
                continue;
            }
            if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)$/u', $trim, $m)) {
                $blocks[] = ['type' => 'image', 'alt' => $m[1], 'src' => $m[2]];
                ++$i;
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.+)$/u', $trim, $m)) {
                $blocks[] = ['type' => 'titre', 'level' => \strlen($m[1]), 'text' => trim($m[2])];
                ++$i;
                continue;
            }
            if (str_starts_with($trim, '>')) {
                $quote = [];
                while ($i < $n && str_starts_with(trim($lines[$i]), '>')) {
                    $quote[] = trim(preg_replace('/^>\s?/', '', trim($lines[$i])));
                    ++$i;
                }
                $blocks[] = ['type' => 'citation', 'text' => implode("\n", $quote)];
                continue;
            }
            if (preg_match('/^(-|\*|\d+\.)\s+/', $trim, $m)) {
                $ordered = ctype_digit(rtrim($m[1], '.'));
                $items = [];
                while ($i < $n && '' !== trim($lines[$i])) {
                    $t = trim($lines[$i]);
                    if (preg_match('/^(-|\*|\d+\.)\s+(.*)$/u', $t, $mm)) {
                        $items[] = $mm[2];
                    } elseif ([] !== $items) {
                        $items[\count($items) - 1] .= ' '.$t; // ligne de suite d'un point
                    } else {
                        break;
                    }
                    ++$i;
                }
                $blocks[] = ['type' => 'liste', 'ordered' => $ordered, 'items' => implode("\n", $items)];
                continue;
            }
            if (str_starts_with($trim, '|') || str_starts_with($trim, '```') || preg_match('/^:::/', $trim)) {
                $raw = [];
                while ($i < $n && '' !== trim($lines[$i])) {
                    $raw[] = $lines[$i];
                    ++$i;
                }
                $blocks[] = ['type' => 'avance', 'text' => implode("\n", $raw)];
                continue;
            }
            // Paragraphe : jusqu'à la ligne vide ou au début d'un autre bloc
            $para = [];
            while ($i < $n && '' !== trim($lines[$i]) && !self::startsBlock(trim($lines[$i]))) {
                $para[] = trim($lines[$i]);
                ++$i;
            }
            if ([] === $para) { // garde-fou : jamais de boucle sans fin
                $blocks[] = ['type' => 'avance', 'text' => $line];
                ++$i;
                continue;
            }
            $blocks[] = ['type' => 'paragraphe', 'text' => implode("\n", $para)];
        }

        return $blocks;
    }

    /** @param list<array<string, mixed>> $blocks blocs déjà vérifiés (sanitize) */
    public static function toMarkdown(array $blocks): string
    {
        $out = [];
        foreach ($blocks as $b) {
            $text = (string) ($b['text'] ?? '');
            $out[] = match ($b['type']) {
                'titre' => str_repeat('#', 3 === ($b['level'] ?? 2) ? 3 : 2).' '.preg_replace('/\s+/u', ' ', $text),
                'liste' => implode("\n", array_map(
                    fn (string $item, int $k) => ($b['ordered'] ? ($k + 1).'. ' : '- ').$item,
                    $items = array_values(array_filter(array_map('trim', explode("\n", (string) $b['items'])), 'strlen')),
                    array_keys($items),
                )),
                'citation' => implode("\n", array_map(fn (string $l) => '> '.$l, array_values(array_filter(array_map('trim', explode("\n", $text)), 'strlen')))),
                'encadre' => ':::'.$b['kind'].('' !== $b['title'] ? ' '.$b['title'] : '')."\n".$text."\n:::",
                'schema' => '[[schema:'.$b['code'].']]',
                'image' => '!['.$b['alt'].']('.$b['src'].')',
                default => $text,
            };
        }

        return implode("\n\n", array_filter($out, fn (string $s) => '' !== trim($s)));
    }

    /**
     * Blocs propres à partir de ce qu'envoie l'éditeur : types et valeurs dans des listes fermées, longueurs bornées.
     * Un bloc vide disparaît ; une image qui ne vient pas du studio ou un schéma au code invalide aussi.
     *
     * @return list<array<string, mixed>>
     */
    public static function sanitize(mixed $input): array
    {
        $out = [];
        foreach (\is_array($input) ? \array_slice(array_values($input), 0, self::MAX_BLOCKS) : [] as $raw) {
            if (!\is_array($raw) || !\in_array($raw['type'] ?? null, self::TYPES, true)) {
                continue;
            }
            $text = self::text($raw['text'] ?? '');
            $block = match ($raw['type']) {
                'paragraphe', 'citation', 'avance' => '' !== trim($text) ? ['type' => $raw['type'], 'text' => $text] : null,
                'titre' => '' !== trim($text) ? ['type' => 'titre', 'level' => 3 === (int) ($raw['level'] ?? 2) ? 3 : 2, 'text' => trim(preg_replace('/\s+/u', ' ', $text))] : null,
                'liste' => '' !== trim(self::text($raw['items'] ?? '')) ? ['type' => 'liste', 'ordered' => (bool) ($raw['ordered'] ?? false), 'items' => self::text($raw['items'] ?? '')] : null,
                'encadre' => isset(LessonMarkdown::CALLOUTS[$raw['kind'] ?? '']) && '' !== trim($text)
                    ? ['type' => 'encadre', 'kind' => $raw['kind'], 'title' => trim(preg_replace('/\s+/u', ' ', mb_substr((string) ($raw['title'] ?? ''), 0, 80))), 'text' => trim(str_replace(':::', '::', $text))] : null,
                'schema' => preg_match('/^[a-z0-9-]{1,80}$/', (string) ($raw['code'] ?? '')) ? ['type' => 'schema', 'code' => $raw['code']] : null,
                'image' => AcademyImages::isPublicPath((string) ($raw['src'] ?? ''))
                    ? ['type' => 'image', 'src' => $raw['src'], 'alt' => trim(str_replace([']', '[', "\n"], ['', '', ' '], mb_substr((string) ($raw['alt'] ?? ''), 0, 160)))] : null,
                default => null,
            };
            if (null !== $block) {
                $out[] = $block;
            }
        }

        return $out;
    }

    private static function startsBlock(string $trim): bool
    {
        return 1 === preg_match('/^(#{2,3}\s|>|(-|\*|\d+\.)\s|\||:::|\[\[schema:|!\[)/u', $trim);
    }

    private static function text(mixed $value): string
    {
        return mb_substr(trim(str_replace(["\r\n", "\r"], "\n", (string) $value), "\n"), 0, self::MAX_TEXT);
    }
}
