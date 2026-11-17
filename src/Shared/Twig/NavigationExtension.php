<?php

namespace App\Shared\Twig;

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
        'plans' => ['label' => 'Mes plans', 'icon' => 'compass', 'match' => ['/plan-de-vie', '/mes-strategies', '/plan-de-croissance', '/plan-de-developpement', '/plan-de-transmission', '/plan-d-hygiene'], 'items' => [
            ['Plan de Vie', 'app_plan_de_vie', 'compass', 'Raison d\'être, valeurs, vision et objectifs'],
            ['Plan de Croissance', 'app_plan_de_croissance', 'sprout', 'Caractère, relations, compétences', 'pro'],
            ['Plan de Développement', 'app_plan_de_developpement', 'graduation-cap', 'Compétences, formations, lectures', 'pro'],
            ['Plan de Transmission', 'app_plan_de_transmission', 'hand-heart', 'Legs, mentorat et service', 'pro'],
            ['Plan d\'Hygiène de Vie', 'app_plan_d_hygiene', 'heart-pulse', 'Sommeil, nutrition, sport, repos', 'pro'],
            ['Vue d\'ensemble de mes plans', 'app_strategies', 'layout-grid', 'Vos quatre plans côte à côte : Être, Faire, Donner'],
        ]],
        'daily' => ['label' => 'Mon quotidien', 'icon' => 'calendar-check', 'match' => ['/mes-plans/rituels', '/planner', '/journal', '/bilan'], 'items' => [
            ['Rituels', 'app_user_habits', 'activity', 'Les petites actions qui nourrissent vos objectifs'],
            ['Agenda', 'app_planner', 'calendar', 'Tâches, jalons et frise'],
            ['Journal', 'app_journal', 'notebook-pen', 'Pensées, prières, victoires'],
            ['Bilan', 'app_bilan', 'bar-chart-2', 'Revues de la semaine et du mois'],
        ]],
        'together' => ['label' => 'Ensemble', 'icon' => 'users', 'match' => ['/social', '/couple', '/defis', '/organisation', '/parrainage'], 'items' => [
            ['Partenaires', 'app_social', 'users', 'Redevabilité et partages'],
            ['Notre couple', 'app_couple', 'heart', 'Vision et projets à deux'],
            ['Défis', 'app_challenges', 'trophy', 'Avancer ensemble sur 21 jours'],
            ['Mon organisation', 'app_organisation_index', 'building-2', 'Église, association…'],
            ['Inviter mes proches', 'app_referral', 'gift', '1 mois PRO offert par ami'],
        ]],
        'method' => ['label' => 'La Méthode', 'icon' => 'library', 'route' => 'app_methode_index', 'match' => ['/methode'], 'items' => []],
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
        foreach (self::SECTIONS as $key => $s) {
            foreach ($s['match'] as $prefix) {
                if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/') || str_starts_with($path, $prefix)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
