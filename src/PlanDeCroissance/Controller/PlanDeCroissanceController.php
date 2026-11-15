<?php

namespace App\PlanDeCroissance\Controller;

use App\PlanDeVie\Controller\AbstractPlanController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan de Croissance : page (hub des stratégies ouvert sur ce plan), visualisation et envoi par email.
 */
#[Route('/plan-de-croissance', name: 'app_plan_de_croissance')]
class PlanDeCroissanceController extends AbstractPlanController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderStrategies('CROISSANCE');
    }

    #[Route('/visualiser', name: '_visualize', methods: ['GET'])]
    public function visualize(): Response
    {
        return $this->renderVisualization('CROISSANCE');
    }

    #[Route('/email', name: '_email', methods: ['POST'])]
    public function email(): JsonResponse
    {
        return $this->sendByEmail('CROISSANCE');
    }
}
