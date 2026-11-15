<?php

namespace App\PlanDeVie\Controller;

use App\Compte\Entity\User;
use App\PlanDeVie\Service\PlanCatalog;
use App\PlanDeVie\Service\PlanDataProvider;
use App\PlanDeVie\Service\PlanMailer;
use App\PlanDeVie\Service\PlanPdfRenderer;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Actions communes aux pages des plans : visualisation, envoi par email,
 * page du hub des stratégies ouverte sur le bon plan.
 */
abstract class AbstractPlanController extends AbstractController
{
    protected PlanDataProvider $plans;
    protected PlanMailer $planMailer;

    #[Required]
    public function setPlanServices(PlanDataProvider $plans, PlanMailer $planMailer): void
    {
        $this->plans = $plans;
        $this->planMailer = $planMailer;
    }

    protected function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }

    /** Hub des stratégies, ouvert sur un plan (null = vue d'ensemble). */
    protected function renderStrategies(?string $planKey): Response
    {
        $data = $this->plans->getStrategiesData($this->user());
        $data['initialPlan'] = $planKey;

        return $this->render('plan_de_vie/strategies.html.twig', $data);
    }

    protected function renderVisualization(string $planKey): Response
    {
        $data = 'VIE' === $planKey ? $this->plans->getLifePlanData($this->user()) : $this->plans->getStrategiesData($this->user());
        $data['variant'] = 'WEB';

        return $this->render(PlanCatalog::PLANS[$planKey]['template'], $data);
    }

    protected function downloadPdf(PlanPdfRenderer $renderer, string $planKey): Response
    {
        return new Response($renderer->render($this->user(), $planKey), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, PlanPdfRenderer::filename($planKey)),
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function sendByEmail(string $planKey): JsonResponse
    {
        if (!$this->planMailer->send($this->user(), $planKey)) {
            return new JsonResponse(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé. Réessayez dans quelques minutes.'], 500);
        }

        return new JsonResponse(['success' => true, 'emailSentTo' => $this->user()->getEmail()]);
    }
}
