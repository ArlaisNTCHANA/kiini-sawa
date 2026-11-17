<?php

namespace App\PlanDeVie\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Mes plans » (/mes-plans) : vue d'ensemble des plans qui découlent du Plan de Vie. L'ancienne adresse
 * /mes-strategies (« stratégie » désigne, dans le livre, un moyen d'atteindre un objectif, pas un plan) redirige ici.
 * Chaque plan a aussi sa propre adresse (/plan-de-croissance…), servie par son module.
 */
class PlansOverviewController extends AbstractPlanController
{
    #[Route('/mes-strategies', name: 'app_strategies_legacy', methods: ['GET'])]
    public function legacy(): RedirectResponse
    {
        return $this->redirectToRoute('app_plans', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/mes-plans', name: 'app_plans', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderStrategies(null);
    }
}
