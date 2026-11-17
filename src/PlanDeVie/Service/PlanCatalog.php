<?php

namespace App\PlanDeVie\Service;

/**
 * Les 5 plans de KIINI SAWA : nom affiché, contenu, routes et gabarit de visualisation.
 * **Source unique** des descriptions (menu, vue d'ensemble). Décision du 15/11/2026 (docs/plan-v1.md §6.1) :
 * l'axe « Compétence & Connaissance » a quitté le Plan de Croissance pour le Plan de Développement ; la Croissance
 * garde « Caractère & Conviction », « Relation & Réseau » et les valeurs d'alignement.
 * Source : docs/charte-projet.md, §1bis « Lexique des 5 plans ». Les clés techniques
 * (VIE, CROISSANCE…) sont aussi celles des piliers de principes et de l'assistant.
 */
final class PlanCatalog
{
    public const PLANS = [
        'VIE' => ['label' => 'Plan de Vie', 'desc' => 'Raison d\'être, valeurs, vision et objectifs.', 'route' => 'app_plan_de_vie', 'template' => 'plan_de_vie/visualize.html.twig'],
        'CROISSANCE' => ['label' => 'Plan de Croissance', 'desc' => 'Caractère & Conviction, Relation & Réseau, Valeurs d\'alignement.', 'route' => 'app_plan_de_croissance', 'template' => 'plan_de_croissance/visualize.html.twig'],
        'DEVELOPPEMENT' => ['label' => 'Plan de Développement', 'desc' => 'Compétences à acquérir, Formations & Livres d\'étude.', 'route' => 'app_plan_de_developpement', 'template' => 'plan_de_developpement/visualize.html.twig'],
        'TRANSMISSION' => ['label' => 'Plan de Transmission', 'desc' => 'Projets de legs, Mentorat & Service.', 'route' => 'app_plan_de_transmission', 'template' => 'plan_de_transmission/visualize.html.twig'],
        'HYGIENE' => ['label' => 'Plan d\'Hygiène de Vie', 'desc' => 'Sommeil, Nutrition, Sport, Méditation & Environnement.', 'route' => 'app_plan_d_hygiene', 'template' => 'plan_dhygiene/visualize.html.twig'],
    ];

    public static function label(string $key): string
    {
        return self::PLANS[$key]['label'];
    }
}
