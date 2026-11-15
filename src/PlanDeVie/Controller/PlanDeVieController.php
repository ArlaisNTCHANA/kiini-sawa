<?php

namespace App\PlanDeVie\Controller;

use App\PlanDeVie\Service\PlanPdfRenderer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plan de Vie : page principale (fondations, vision, objectifs, carte, analyse de cohérence),
 * visualisation imprimable et envoi par email.
 */
#[Route('/plan-de-vie', name: 'app_plan_de_vie')]
class PlanDeVieController extends AbstractPlanController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('plan_de_vie/index.html.twig', $this->plans->getLifePlanData($this->user()));
    }

    #[Route('/visualiser', name: '_visualize', methods: ['GET'])]
    public function visualize(): Response
    {
        return $this->renderVisualization('VIE');
    }

    #[Route('/pdf', name: '_pdf', methods: ['GET'])]
    public function pdf(PlanPdfRenderer $renderer): Response
    {
        return $this->downloadPdf($renderer, 'VIE');
    }

    #[Route('/email', name: '_email', methods: ['POST'])]
    public function email(): JsonResponse
    {
        return $this->sendByEmail('VIE');
    }
}
