<?php

namespace App\PlanDeVie\Controller;

use App\Compte\Entity\User;
use App\Abonnement\Service\Entitlements;
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
    protected Entitlements $entitlements;

    #[Required]
    public function setPlanServices(PlanDataProvider $plans, PlanMailer $planMailer, Entitlements $entitlements): void
    {
        $this->plans = $plans;
        $this->planMailer = $planMailer;
        $this->entitlements = $entitlements;
    }

    /** Plan stratégique réservé à PRO : redirige vers la page d'abonnement. */
    protected function requirePlan(string $planKey): void
    {
        if (isset(Entitlements::PLAN_FEATURES[$planKey])) {
            $this->entitlements->require($this->user(), Entitlements::PLAN_FEATURES[$planKey]);
        }
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
        if (null !== $planKey) {
            $this->requirePlan($planKey);
        }
        $data = $this->plans->getStrategiesData($this->user());
        $data['initialPlan'] = $planKey;
        $data['lockedPlans'] = $this->entitlements->lockedPlans($this->user());

        return $this->render('plan_de_vie/strategies.html.twig', $data);
    }

    protected function renderVisualization(string $planKey): Response
    {
        $this->requirePlan($planKey);
        $data = 'VIE' === $planKey ? $this->plans->getLifePlanData($this->user()) : $this->plans->getStrategiesData($this->user());
        $data['variant'] = 'WEB';

        return $this->render(PlanCatalog::PLANS[$planKey]['template'], $data);
    }

    protected function downloadPdf(PlanPdfRenderer $renderer, string $planKey): Response
    {
        $this->entitlements->require($this->user(), 'pdf');
        $this->requirePlan($planKey);
        return new Response($renderer->render($this->user(), $planKey), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, PlanPdfRenderer::filename($planKey)),
            'Cache-Control' => 'no-store',
        ]);
    }

    protected function sendByEmail(string $planKey): JsonResponse
    {
        $this->requirePlan($planKey);
        if (!$this->planMailer->send($this->user(), $planKey)) {
            return new JsonResponse(['success' => false, 'error' => 'L\'email n\'a pas pu être envoyé. Réessayez dans quelques minutes.'], 500);
        }

        return new JsonResponse(['success' => true, 'emailSentTo' => $this->user()->getEmail()]);
    }
}
