<?php

namespace App\Methode\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Chapitres du livre affichés dans l'application (content/livre/*.md).
 *
 * Chaque écran de méthode peut afficher un encart « Comprendre cette étape »
 * relié à son chapitre : voir STEPS et la fonction Twig book_panel().
 */
final class BookLibrary
{
    /** Nombre de premiers chapitres lisibles sans compte (Comment lire ce livre, introduction, chapitre 1). */
    public const PUBLIC_CHAPTERS = 3;

    /**
     * Ordre de lecture : fichier => identifiant d'URL. Les fichiers sont produits par app:livre:sync à partir du
     * manuscrit (livre-et-ressources/sources/livre/manuscrit.md) ; la dédicace et la page auteur ne sont pas publiées.
     */
    public const CHAPTERS = [
        '00_COMMENT_LIRE.md' => 'comment-lire',
        '01_INTRODUCTION.md' => 'introduction',
        'CHAP_01.md' => 'danger-de-comparer',
        'CHAP_02.md' => 'plan-et-providence',
        'CHAP_03.md' => 'clarifier-les-notions',
        'CHAP_04.md' => 'but-de-dieu',
        'CHAP_05.md' => 'balance-interne',
        'CHAP_06.md' => 'passions',
        'CHAP_07.md' => 'dons-et-talents',
        'CHAP_08.md' => 'personnalite',
        'CHAP_09.md' => 'vision',
        'CHAP_10.md' => 'objectifs',
        'CHAP_11.md' => 'saisons-et-echeancier',
        'CHAP_12.md' => 'confirmations',
        'CHAP_13.md' => 'puissance-sans-plan',
        'CHAP_14.md' => 'caractere-et-conviction',
        'CHAP_15.md' => 'relation-et-reseau',
        'CHAP_16.md' => 'competence-et-connaissance',
        'CHAP_17.md' => 'hygiene-de-vie',
        'CHAP_18.md' => 'intendance',
        'CHAP_19.md' => 'perspective-de-l-eternite',
        'CHAP_20.md' => 'heritage-et-transmission',
        'CHAP_21.md' => 'carte-strategique',
        'CHAP_22.md' => 'habitudes',
        'CHAP_23.md' => 'revues-feedback-et-defis',
        'CHAP_24.md' => 'quand-le-plan-echoue',
        '90_CONCLUSION.md' => 'conclusion',
        '91_ANNEXES.md' => 'annexes',
    ];

    /** Anciennes adresses (première version du livre) => chapitre actuel, pour ne pas casser les liens. */
    public const LEGACY_SLUGS = [
        'synthese' => 'comment-lire',
        'vision-et-objectifs' => 'vision',
        'echeancier-et-saisons' => 'saisons-et-echeancier',
        'pourquoi-croitre' => 'puissance-sans-plan',
        'les-3-axes' => 'puissance-sans-plan',
        'enracinement' => 'habitudes',
        'revues-et-bilans' => 'revues-feedback-et-defis',
        'decouverte-et-feedback' => 'revues-feedback-et-defis',
        'defis' => 'revues-feedback-et-defis',
    ];

    /** Écran de l'application => chapitre qui l'explique. */
    public const STEPS = [
        'wizard.1' => 'but-de-dieu',
        'wizard.2' => 'balance-interne',
        'wizard.3' => 'passions',
        'wizard.4' => 'dons-et-talents',
        'wizard.5' => 'personnalite',
        'wizard.6' => 'vision',
        'wizard.7' => 'objectifs',
        'wizard.8' => 'competence-et-connaissance',
        'wizard.9' => 'caractere-et-conviction',
        'wizard.10' => 'relation-et-reseau',
        'wizard.11' => 'heritage-et-transmission',
        'wizard.12' => 'hygiene-de-vie',
        'wizard.13' => 'saisons-et-echeancier',
        'plan_de_vie' => 'comment-lire',
        'strategies' => 'carte-strategique',
        'intendance' => 'intendance',
        'rituels' => 'habitudes',
        'bilan' => 'revues-feedback-et-defis',
        'revue' => 'revues-feedback-et-defis',
        'personnalite' => 'personnalite',
    ];

    private array $cache = [];

    public function __construct(#[Autowire('%kernel.project_dir%/content/livre')] private readonly string $dir)
    {
    }

    /** @return list<array{slug: string, title: string, excerpt: string, minutes: int}> */
    public function all(): array
    {
        return array_values(array_filter(array_map(fn (string $slug) => $this->get($slug), self::CHAPTERS)));
    }

    /** Chapitre lisible par un visiteur non inscrit ? */
    public function isPublic(string $slug): bool
    {
        $i = array_search($slug, array_values(self::CHAPTERS), true);

        return false !== $i && $i < self::PUBLIC_CHAPTERS;
    }

    /** @return array{slug: string, title: string, excerpt: string, html: string, minutes: int, previous: ?string, next: ?string}|null */
    public function get(string $slug): ?array
    {
        if (isset($this->cache[$slug])) {
            return $this->cache[$slug];
        }
        $file = array_search($slug, self::CHAPTERS, true);
        if (false === $file || !is_file($path = $this->dir.'/'.$file)) {
            return null;
        }

        $markdown = (string) file_get_contents($path);
        preg_match('/^#\s+(.+)$/m', $markdown, $m);
        $title = trim(preg_replace('/^[^\p{L}\d]+/u', '', $m[1] ?? $slug));
        $body = preg_replace('/^#\s+.+$/m', '', $markdown, 1);
        // GitHub admonitions (> [!TIP]) : marqueur retiré, la citation reste
        $body = preg_replace('/^>\s*\[!(TIP|NOTE|IMPORTANT|WARNING)\]\s*$/m', '>', (string) $body);

        $parser = new \Parsedown();
        $parser->setSafeMode(true);

        $slugs = array_values(self::CHAPTERS);
        $i = array_search($slug, $slugs, true);

        return $this->cache[$slug] = [
            'slug' => $slug,
            'title' => $title,
            'excerpt' => $this->excerpt((string) $body),
            'html' => $parser->text((string) $body),
            'minutes' => max(1, (int) round(str_word_count(strip_tags($body)) / 200)),
            'previous' => $slugs[$i - 1] ?? null,
            'next' => $slugs[$i + 1] ?? null,
        ];
    }

    public function forStep(string $key): ?array
    {
        return isset(self::STEPS[$key]) ? $this->get(self::STEPS[$key]) : null;
    }

    /** Premier vrai paragraphe (ni citation, ni titre, ni liste), sans mise en forme. */
    private function excerpt(string $body): string
    {
        foreach (preg_split('/\n\s*\n/', $body) as $block) {
            $block = trim($block);
            if ('' === $block || preg_match('/^(>|#|-|\*|\d+\.|---|\|)/', $block)) {
                continue;
            }
            $text = trim(preg_replace('/\s+/', ' ', str_replace(['**', '__', '*', '`'], '', $block)));
            if (mb_strlen($text) > 60) {
                return mb_strlen($text) > 320 ? mb_substr($text, 0, 317).'…' : $text;
            }
        }

        return '';
    }
}
