<?php

namespace App\PlanDeVie\Controller;

use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Service\LifePlanWizardSaver;
use App\PlanDeVie\Service\PlanCatalog;
use App\PlanDeVie\Service\PlanSnapshotService;
use App\PlanDeVie\Service\TestResultsForPlans;
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
    public function wizard(Request $request, LifePlanWizardSaver $saver, EntityManagerInterface $em, PlanSnapshotService $snapshots, TestResultsForPlans $testResults): Response
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
        // Plan stratégique réservé à PRO (y compris une étape isolée appartenant à ce plan)
        $this->requirePlan($plan ?: (string) array_key_first(array_filter(LifePlanWizardSaver::STEPS_BY_PLAN, fn ($steps, $key) => '' !== $key && \in_array($step, $steps, true), \ARRAY_FILTER_USE_BOTH)));

        if ($request->isMethod('POST')) {
            // Protection CSRF : contrôle de l'origine (SameOriginRequestSubscriber) ET jeton du formulaire
            if (!$this->isCsrfTokenValid('plan_wizard', $request->request->getString('_csrf_token'))) {
                if ($request->request->getBoolean('autosave')) {
                    return new JsonResponse(['success' => false, 'error' => 'La page a expiré.'], 400);
                }
                $this->addFlash('error', 'La page a expiré. Vos réponses n\'ont pas été enregistrées : merci de réessayer.');

                return $this->redirectToRoute('app_plan_wizard', ['step' => $step, 'single' => $single ? 1 : 0, 'plan' => $plan]);
            }
            // Enregistrement de l'étape quittée (« Suivant »)
            if ($request->request->getBoolean('autosave')) {
                $autosaveStep = $request->request->getInt('autosave_step');
                $ids = \in_array($autosaveStep, $allowedSteps, true) || ($single && $autosaveStep === $step)
                    ? $saver->save($user, $request->request, [$autosaveStep])
                    : [];

                return new JsonResponse(['success' => true, 'ids' => $ids]);
            }

            $saver->save($user, $request->request, $single ? [$step] : $allowedSteps);
            if (!$single) {
                $snapshots->snapshotOncePerDay($user, 'Après l\'assistant · '.PlanCatalog::label($plan ?: 'VIE'));
            }
            if ('' !== $plan) {
                $this->addFlash('success', 'Votre plan a été enregistré.');

                return $this->redirectToRoute(PlanCatalog::PLANS[$plan]['route']);
            }
            if ($single) {
                $this->addFlash('success', 'Votre plan a été enregistré.');

                return $this->redirectToRoute('app_plan_de_vie');
            }

            // Point 3 (26/11/2026) : fin de l'assistant du Plan de Vie : on félicite, avec le score de cohérence et les
            // premières pistes, sur la page du plan (onglet Analyse ouvert) plutôt qu'un simple bandeau sur Aujourd'hui
            $this->addFlash('plan_bravo', true);

            return $this->redirectToRoute('app_plan_de_vie', ['onglet' => 'analysis']);
        }

        if (null === $user->getPlanDeVie()) {
            $planDeVie = new PlanDeVie();
            $user->setPlanDeVie($planDeVie); // relie aussi le côté utilisateur, utilisé juste après
            $em->persist($planDeVie);
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
            'testResults' => $testResults->forUser($user),
            'discOptions' => TestResultsForPlans::discOptions(),
        ]));
    }
}
