<?php

namespace App\PlanDHygiene\Controller;

use App\PlanDeVie\Controller\AbstractPlanController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan d'Hygiène de vie : page (hub des stratégies ouvert sur ce plan), visualisation et envoi par e-mail.
 */
#[Route('/plan-d-hygiene', name: 'app_plan_d_hygiene')]
class PlanDHygieneController extends AbstractPlanController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->renderStrategies('HYGIENE');
    }

    #[Route('/visualiser', name: '_visualize', methods: ['GET'])]
    public function visualize(): Response
    {
        return $this->renderVisualization('HYGIENE');
    }

    #[Route('/email', name: '_email', methods: ['POST'])]
    public function email(): JsonResponse
    {
        return $this->sendByEmail('HYGIENE');
    }
}
