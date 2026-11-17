<?php

namespace App\PlanDeVie\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * « Vue d'ensemble de mes plans » (ancien « Mes stratégies ») : vue d'ensemble des plans qui découlent du Plan de Vie.
 * Chaque plan a aussi sa propre adresse (/plan-de-croissance…), servie par son module.
 */
class StrategiesController extends AbstractPlanController
{
    #[Route('/mes-strategies', name: 'app_strategies', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderStrategies(null);
    }
}
