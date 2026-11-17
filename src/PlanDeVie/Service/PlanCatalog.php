<?php

namespace App\PlanDeVie\Service;

/**
 * Les 6 plans de KIINI SAWA : nom affiché, formule, contenu, routes et gabarit de visualisation.
 * Ordre, noms et formules : le livre (livre-et-ressources/sources/livre/manuscrit.md, « Comment lire ce livre »),
 * Plan d'Intendance ajouté par l'auteur le 17/11/2026. La clé « question » porte la formule du livre.
 * **Source unique** des descriptions (menu, vue d'ensemble). Décision du 15/11/2026 (docs/plan-v1.md §6.1) :
 * l'axe « Compétence & Connaissance » a quitté le Plan de Croissance pour le Plan de Développement personnel ; la Croissance
 * garde « Caractère & Conviction », « Relation & Réseau » et les valeurs d'alignement.
 * Lexique : docs/lexique.md, « Les 6 plans de la méthode ». Les clés techniques
 * (VIE, CROISSANCE…) sont aussi celles des piliers de principes et de l'assistant.
 */
final class PlanCatalog
{
    public const PLANS = [
        'VIE' => ['label' => 'Plan de Vie', 'question' => 'Qui je suis, où je vais', 'desc' => 'But de Dieu, valeurs, passions, dons, vision et objectifs.', 'route' => 'app_plan_de_vie', 'template' => 'plan_de_vie/visualize.html.twig'],
        'CROISSANCE' => ['label' => 'Plan de Croissance', 'question' => 'Qui je deviens', 'desc' => 'Caractère & Conviction, Relation & Réseau.', 'route' => 'app_plan_de_croissance', 'template' => 'plan_de_croissance/visualize.html.twig'],
        'DEVELOPPEMENT' => ['label' => 'Plan de Développement personnel', 'question' => 'Ce que je développe', 'desc' => 'Compétence & Connaissance : formations, livres d\'étude, route professionnelle.', 'route' => 'app_plan_de_developpement', 'template' => 'plan_de_developpement/visualize.html.twig'],
        'HYGIENE' => ['label' => 'Plan d\'Hygiène de vie', 'question' => 'Ce qui me soutient', 'desc' => 'Corps, âme et esprit : les rythmes qui vous soutiennent.', 'route' => 'app_plan_d_hygiene', 'template' => 'plan_dhygiene/visualize.html.twig'],
        'INTENDANCE' => ['label' => 'Plan d\'Intendance', 'question' => 'Ce que je gère', 'desc' => 'Argent, temps, foyer, dons et tout ce qui vous est confié.', 'route' => 'app_plan_d_intendance', 'template' => 'plan_d_intendance/visualize.html.twig'],
        'TRANSMISSION' => ['label' => 'Plan de Transmission', 'question' => 'Ce que je laisse', 'desc' => 'Valeurs, œuvres et personnes qui porteront du fruit après vous.', 'route' => 'app_plan_de_transmission', 'template' => 'plan_de_transmission/visualize.html.twig'],
    ];

    public static function label(string $key): string
    {
        return self::PLANS[$key]['label'];
    }
}
