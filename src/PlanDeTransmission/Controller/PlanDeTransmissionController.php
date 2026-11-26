<?php

namespace App\PlanDeTransmission\Controller;

use App\PlanDeVie\Controller\AbstractPlanController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan de Transmission : page (hub des stratégies ouvert sur ce plan), visualisation et envoi par e-mail.
 */
#[Route('/plan-de-transmission', name: 'app_plan_de_transmission')]
class PlanDeTransmissionController extends AbstractPlanController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderStrategies('TRANSMISSION');
    }

    #[Route('/visualiser', name: '_visualize', methods: ['GET'])]
    public function visualize(): Response
    {
        return $this->renderVisualization('TRANSMISSION');
    }

    #[Route('/email', name: '_email', methods: ['POST'])]
    public function email(): JsonResponse
    {
        return $this->sendByEmail('TRANSMISSION');
    }
}
