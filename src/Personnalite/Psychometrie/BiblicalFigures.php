<?php

namespace App\Personnalite\Psychometrie;

/**
 * Bibliothèque de fiches de personnages bibliques (pilote : Élie, Joseph, Timothée).
 *
 * Cadre doctrinal (fondateur, 18/11/2026) : le Christ seul et son œuvre ; l'Ancien Testament pointe vers le Christ ; la grâce, jamais le
 * légalisme. Version biblique : Louis Segond 1910 (domaine public).
 *
 * Chaque fiche sépare strictement :
 *  - « Dans le texte » : ce que le récit biblique dit (références et citations vérifiables) ;
 *  - « Son profil » (en tête, pour fonder la crédibilité) : les indices de comportement que le texte donne pour chaque dimension DISC, avec
 *    leur référence et la solidité de l'indice, plus ce que le texte ne montre pas (les auteurs bibliques ne pensaient pas en DISC) ;
 *  - « Le Christ au centre » : comment ce personnage pointe vers le Christ et vers la grâce.
 *
 * Toutes les citations sont celles de la LSG ; `tests/Personnalite/BiblicalFiguresTest.php` les vérifie contre le texte quand il est
 * présent (var/bible/lsg.json, voir bin/outils/bible.py). Contenu marqué « en cours de relecture » tant que le champ `relecture` ne vaut pas « faite » (modifiable dans l'administration).
 */
final class BiblicalFigures
{
    /** Les fiches sont dans `content/personnages/*.json` (source) ; les lectures par test (ennéagramme, dons, type) dans `content/lectures/*.json`. */
    public const FIGURES = [];

    /** @var ?array<string, array> */
    private static ?array $raw = null;

    /**
     * Fiches de référence (code et fichiers JSON) : les fiches pilotes ci-dessus et celles de `content/personnages/*.json`
     * (une fiche par clé, même structure ; ces fichiers sont la source).
     *
     * @return array<string, array>
     */
    public static function raw(): array
    {
        if (null === self::$raw) {
            self::$raw = self::FIGURES;
            $root = \dirname(__DIR__, 3).'/content';
            foreach (glob($root.'/personnages/*.json') ?: [] as $file) {
                foreach (json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR) as $key => $fig) {
                    self::$raw[$key] = $fig;
                }
            }
            // Lectures par test : { CLÉ: { lenses: { "ENN:5": {...} } } } ajoutées aux fiches existantes
            foreach (glob($root.'/lectures/*.json') ?: [] as $file) {
                foreach (json_decode((string) file_get_contents($file), true, 512, \JSON_THROW_ON_ERROR) as $key => $extra) {
                    if (isset(self::$raw[$key])) {
                        self::$raw[$key]['lenses'] = ($extra['lenses'] ?? []) + (self::$raw[$key]['lenses'] ?? []);
                    }
                }
            }
        }

        return self::$raw;
    }

    /**
     * Toutes les fiches telles qu'on les affiche : référence + textes modifiés dans l'administration
     * (Admin › Textes des tests › Personnages bibliques ; source `figures.library`).
     *
     * @return array<string, array>
     */
    public static function all(): array
    {
        return Content::get('figures.library');
    }

    /** Fiche d'un personnage d'après son nom tel qu'il apparaît dans les profils, ou null s'il n'a pas encore de fiche. */
    public static function find(string $name): ?array
    {
        foreach (self::all() as $key => $fig) {
            if (\in_array($name, $fig['aliases'], true)) {
                return $fig + ['key' => $key];
            }
        }

        return null;
    }

    /** Pourquoi cette figure parle à ce profil DISC (dimension principale), avec un texte par défaut. */
    public static function why(array $fig, string $discLetter): string
    {
        return $fig['why'][$discLetter] ?? $fig['why']['default'];
    }
}
