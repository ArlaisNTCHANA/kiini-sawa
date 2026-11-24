<?php

namespace App\Shared\Twig;

use App\PlanDeVie\Service\PlanCatalog;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Navigation de l'application, définie une seule fois pour l'ordinateur et le téléphone.
 * Six rubriques qui suivent la méthode : Aujourd'hui → Me connaître → Mes plans → Mon quotidien → Ensemble → La Méthode.
 * Le compte (profil, abonnement, installation, avis) est à part. Voir docs/design.md (navigation).
 */
final class NavigationExtension extends AbstractExtension
{
    /** Rubrique => [libellé, icône, préfixes d'adresse, pages [libellé, route, icône, description]]. */
    public const SECTIONS = [
        'today' => ['label' => 'Aujourd\'hui', 'icon' => 'sun', 'route' => 'app_dashboard', 'match' => ['/dashboard', '/notifications'], 'items' => []],
        'know' => ['label' => 'Me connaître', 'icon' => 'sparkles', 'match' => ['/mes-tests'], 'items' => [
            ['Mes tests', 'app_personality_index', 'sparkles', 'Personnalité, dons, auto-évaluations'],
            ['Mon profil KIINI SAWA', 'app_synthesis', 'fingerprint', 'La synthèse de tous vos tests'],
            ['Mon évolution', 'app_test_evolution', 'trending-up', 'Vos tests au fil du temps'],
            ['Feedback nourricier', 'app_feedback', 'message-circle-heart', 'Le regard bienveillant de vos proches'],
        ]],
        'plans' => ['label' => 'Mes plans', 'icon' => 'compass', 'match' => ['/plan-de-vie', '/mes-plans', '/plan-de-croissance', '/plan-de-developpement', '/plan-de-transmission', '/plan-d-hygiene', '/plan-d-intendance'], 'items' => [
            // Question et contenu : source unique PlanCatalog ; ordre et icônes de la méthode
            ['Plan de Vie', 'app_plan_de_vie', 'compass', PlanCatalog::PLANS['VIE']['question'].'. '.PlanCatalog::PLANS['VIE']['desc']],
            ['Plan de Croissance', 'app_plan_de_croissance', 'sprout', PlanCatalog::PLANS['CROISSANCE']['question'].'. '.PlanCatalog::PLANS['CROISSANCE']['desc'], 'pro'],
            ['Plan de Développement personnel', 'app_plan_de_developpement', 'wrench', PlanCatalog::PLANS['DEVELOPPEMENT']['question'].'. '.PlanCatalog::PLANS['DEVELOPPEMENT']['desc'], 'pro'],
            ['Plan d\'Hygiène de vie', 'app_plan_d_hygiene', 'heart-pulse', PlanCatalog::PLANS['HYGIENE']['question'].'. '.PlanCatalog::PLANS['HYGIENE']['desc'], 'pro'],
            ['Plan d\'Intendance', 'app_plan_d_intendance', 'hand-coins', PlanCatalog::PLANS['INTENDANCE']['question'].'. '.PlanCatalog::PLANS['INTENDANCE']['desc'], 'pro'],
            ['Plan de Transmission', 'app_plan_de_transmission', 'hand-heart', PlanCatalog::PLANS['TRANSMISSION']['question'].'. '.PlanCatalog::PLANS['TRANSMISSION']['desc'], 'pro'],
            ['Tous mes plans', 'app_plans', 'layout-grid', 'Vos plans côte à côte : Être, Faire, Avoir, Donner'],
        ]],
        'daily' => ['label' => 'Mon quotidien', 'icon' => 'calendar-check', 'desc' => 'Le système qui relie vos 6 plans au quotidien : rituels, agenda, journal, revues.', 'match' => ['/mes-plans/rituels', '/planner', '/journal', '/bilan'], 'items' => [
            ['Rituels', 'app_user_habits', 'activity', 'Les petites actions qui nourrissent vos objectifs'],
            ['Agenda', 'app_planner', 'calendar', 'Tâches, jalons et frise'],
            ['Journal', 'app_journal', 'notebook-pen', 'Pensées, prières, victoires'],
            ['Bilan', 'app_bilan', 'bar-chart-2', 'Revues de la semaine et du mois'],
            ['Mes rythmes', 'app_rhythms', 'repeat', 'Jour, semaine, mois, saison, année : ce qui est fait ou en retard'],
        ]],
        'together' => ['label' => 'Ensemble', 'icon' => 'users', 'match' => ['/social', '/couple', '/defis', '/organisation', '/parrainage'], 'items' => [
            ['Partenaires', 'app_social', 'users', 'Redevabilité et partages'],
            ['Ma redevabilité', 'app_accountability', 'shield-check', 'Être interpellé(e) par une personne de confiance'],
            ['Notre couple', 'app_couple', 'heart', 'Vision et projets à deux'],
            ['Défis', 'app_challenges', 'trophy', 'Avancer ensemble sur 21 jours'],
            ['Mon organisation', 'app_organisation_index', 'building-2', 'Église, association…'],
            ['Inviter mes proches', 'app_referral', 'gift', '1 mois PRO offert par ami'],
        ]],
        'method' => ['label' => 'La Méthode', 'icon' => 'library', 'route' => 'app_methode_index', 'match' => ['/methode', '/academie'], 'items' => [
            ['Le livre', 'app_methode_index', 'library', 'La méthode KIINI SAWA, chapitre par chapitre'],
            ['L\'Académie', 'app_academie', 'graduation-cap', 'Des parcours courts pour passer à l\'action dans vos plans'],
        ]],
    ];

    /** Barre du bas sur téléphone : les usages quotidiens, puis « Menu ». */
    public const MOBILE_BAR = [
        ['Aujourd\'hui', 'app_dashboard', 'sun', 'today'],
        ['Mes plans', 'app_plan_de_vie', 'compass', 'plans'],
        ['Rituels', 'app_user_habits', 'activity', 'daily'],
        ['Journal', 'app_journal', 'notebook-pen', 'daily'],
    ];

    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nav_sections', fn () => self::SECTIONS),
            new TwigFunction('nav_mobile_bar', fn () => self::MOBILE_BAR),
            new TwigFunction('nav_active_section', $this->activeSection(...)),
        ];
    }

    /** Rubrique de la page affichée (null si aucune). */
    public function activeSection(): ?string
    {
        $path = $this->requests->getCurrentRequest()?->getPathInfo() ?? '';
        $best = null;
        $bestLength = -1;
        foreach (self::SECTIONS as $key => $s) {
            foreach ($s['match'] as $prefix) {
                // Le préfixe le plus long l'emporte : /mes-plans/rituels (Mon quotidien) avant /mes-plans (Mes plans)
                if (str_starts_with($path, $prefix) && \strlen($prefix) > $bestLength) {
                    $best = $key;
                    $bestLength = \strlen($prefix);
                }
            }
        }

        return $best;
    }
}
