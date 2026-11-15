<?php

namespace App\PlanDeVie\Controller;

use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Service\LifePlanWizardSaver;
use App\PlanDeVie\Service\PlanCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Assistant de saisie des plans.
 *
 * - `?plan=` choisit le plan (vide = Plan de Vie) et donc les étapes proposées ;
 * - `?step=N&single=1` ouvre une seule étape (bouton « Corriger » d'une analyse) ;
 * - chaque clic sur « Suivant » enregistre l'étape quittée (requête JSON `autosave`) :
 *   rien n'est perdu si l'utilisateur s'arrête en cours de route.
 */
class WizardController extends AbstractPlanController
{
    #[Route('/plan-de-vie/assistant', name: 'app_plan_wizard', methods: ['GET', 'POST'])]
    public function wizard(Request $request, LifePlanWizardSaver $saver, EntityManagerInterface $em): Response
    {
        $user = $this->user();
        $plan = strtoupper($request->query->getString('plan'));
        $plan = isset(LifePlanWizardSaver::STEPS_BY_PLAN[$plan]) ? $plan : '';
        $allowedSteps = LifePlanWizardSaver::STEPS_BY_PLAN[$plan];
        $single = $request->query->getBoolean('single');
        $step = $request->query->getInt('step', $allowedSteps[0]);
        if ($step < 1 || $step > 13 || (!$single && !\in_array($step, $allowedSteps, true))) {
            $step = $allowedSteps[0];
        }

        if ($request->isMethod('POST')) {
            // Protection CSRF : contrôle de l'origine (SameOriginRequestSubscriber), comme les autres formulaires des plans
            // Enregistrement de l'étape quittée (« Suivant »)
            if ($request->request->getBoolean('autosave')) {
                $autosaveStep = $request->request->getInt('autosave_step');
                $ids = \in_array($autosaveStep, $allowedSteps, true) || ($single && $autosaveStep === $step)
                    ? $saver->save($user, $request->request, [$autosaveStep])
                    : [];

                return new JsonResponse(['success' => true, 'ids' => $ids]);
            }

            $saver->save($user, $request->request, $single ? [$step] : $allowedSteps);
            $this->addFlash('success', 'Votre plan a été enregistré.');

            if ('' !== $plan) {
                return $this->redirectToRoute(PlanCatalog::PLANS[$plan]['route']);
            }

            return $this->redirectToRoute($single ? 'app_plan_de_vie' : 'app_dashboard');
        }

        if (null === $user->getPlanDeVie()) {
            $em->persist((new PlanDeVie())->setUser($user));
            $em->flush();
        }

        $currentWizardStep = array_search($step, $allowedSteps, true);

        return $this->render('plan_de_vie/wizard.html.twig', array_merge($this->plans->getWizardData($user), [
            'step' => $step,
            'single' => $single,
            'plan' => $plan ?: null,
            'planLabel' => PlanCatalog::label($plan ?: 'VIE'),
            'planRoute' => PlanCatalog::PLANS[$plan ?: 'VIE']['route'],
            'allowedSteps' => $allowedSteps,
            'currentWizardStep' => false === $currentWizardStep ? 1 : $currentWizardStep + 1,
        ]));
    }
}
