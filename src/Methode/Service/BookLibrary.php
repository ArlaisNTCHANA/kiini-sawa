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
    /** Ordre de lecture : fichier => identifiant d'URL. */
    /** Nombre de premiers chapitres lisibles sans compte (introduction, synthèse, chapitre 1). */
    public const PUBLIC_CHAPTERS = 3;

    public const CHAPTERS = [
        'INTRO.md' => 'introduction',
        'SUMMARY_PLANS.md' => 'synthese',
        'CHAP_01_BUT_DE_DIEU.md' => 'but-de-dieu',
        'CHAP_02_BALANCE_INTERNE.md' => 'balance-interne',
        'CHAP_03_PASSIONS_INTERETS.md' => 'passions',
        'CHAP_04_DONS_TALENTS.md' => 'dons-et-talents',
        'CHAP_05_VISION_OBJECTIFS.md' => 'vision-et-objectifs',
        'CHAP_06_ECHEANCIER_SAISONS.md' => 'echeancier-et-saisons',
        'CHAP_07_CONFIRMATIONS.md' => 'confirmations',
        'CHAP_08_POURQUOI_CROITRE.md' => 'pourquoi-croitre',
        'CHAP_09_LES_3_AXES.md' => 'les-3-axes',
        'CHAP_10_CARTE_STRATEGIQUE.md' => 'carte-strategique',
        'CHAP_11_HABITUDES_ORGANISATEUR.md' => 'habitudes',
        'CHAP_12_ENRACINEMENT.md' => 'enracinement',
        'CHAP_13_REVUES_BILANS.md' => 'revues-et-bilans',
        'CHAP_14_DECOUVERTE_FEEDBACK.md' => 'decouverte-et-feedback',
        'CHAP_15_DEFIS_CROISSANCE.md' => 'defis',
        'CONCLUSION.md' => 'conclusion',
    ];

    /** Écran de l'application => chapitre qui l'explique. */
    public const STEPS = [
        'wizard.1' => 'but-de-dieu',
        'wizard.2' => 'balance-interne',
        'wizard.3' => 'passions',
        'wizard.4' => 'dons-et-talents',
        'wizard.5' => 'decouverte-et-feedback',
        'wizard.6' => 'vision-et-objectifs',
        'wizard.7' => 'vision-et-objectifs',
        'wizard.8' => 'les-3-axes',
        'wizard.9' => 'les-3-axes',
        'wizard.10' => 'les-3-axes',
        'wizard.12' => 'habitudes',
        'wizard.13' => 'echeancier-et-saisons',
        'plan_de_vie' => 'synthese',
        'strategies' => 'carte-strategique',
        'rituels' => 'enracinement',
        'bilan' => 'revues-et-bilans',
        'revue' => 'revues-et-bilans',
        'personnalite' => 'decouverte-et-feedback',
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
