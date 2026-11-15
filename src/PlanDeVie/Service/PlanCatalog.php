<?php

namespace App\PlanDeVie\Service;

/**
 * Les 5 plans de KIINI SAWA : nom affiché, routes et gabarit de visualisation.
 * Source : docs/charte-projet.md, §1bis « Lexique des 5 plans ». Les clés techniques
 * (VIE, CROISSANCE…) sont aussi celles des piliers de principes et de l'assistant.
 */
final class PlanCatalog
{
    public const PLANS = [
        'VIE' => ['label' => 'Plan de Vie', 'route' => 'app_plan_de_vie', 'template' => 'plan_de_vie/visualize.html.twig'],
        'CROISSANCE' => ['label' => 'Plan de Croissance', 'route' => 'app_plan_de_croissance', 'template' => 'plan_de_croissance/visualize.html.twig'],
        'DEVELOPPEMENT' => ['label' => 'Plan de Développement', 'route' => 'app_plan_de_developpement', 'template' => 'plan_de_developpement/visualize.html.twig'],
        'TRANSMISSION' => ['label' => 'Plan de Transmission', 'route' => 'app_plan_de_transmission', 'template' => 'plan_de_transmission/visualize.html.twig'],
        'HYGIENE' => ['label' => 'Plan d\'Hygiène de Vie', 'route' => 'app_plan_d_hygiene', 'template' => 'plan_dhygiene/visualize.html.twig'],
    ];

    public static function label(string $key): string
    {
        return self::PLANS[$key]['label'];
    }
}
