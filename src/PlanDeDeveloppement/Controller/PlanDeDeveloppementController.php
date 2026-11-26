<?php

namespace App\PlanDeDeveloppement\Controller;

use App\PlanDeVie\Controller\AbstractPlanController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan de Développement personnel : page (hub des stratégies ouvert sur ce plan), visualisation et envoi par e-mail.
 */
#[Route('/plan-de-developpement', name: 'app_plan_de_developpement')]
class PlanDeDeveloppementController extends AbstractPlanController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderStrategies('DEVELOPPEMENT');
    }

    #[Route('/visualiser', name: '_visualize', methods: ['GET'])]
    public function visualize(): Response
    {
        return $this->renderVisualization('DEVELOPPEMENT');
    }

    #[Route('/email', name: '_email', methods: ['POST'])]
    public function email(): JsonResponse
    {
        return $this->sendByEmail('DEVELOPPEMENT');
    }
}
