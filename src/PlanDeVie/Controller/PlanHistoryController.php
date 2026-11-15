<?php

namespace App\PlanDeVie\Controller;

use App\PlanDeVie\Entity\PlanHistory;
use App\PlanDeVie\Service\PlanSnapshotService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Historique des plans : versions datées, consultables en lecture seule.
 */
#[Route('/plan-de-vie/historique', name: 'app_plan_history')]
class PlanHistoryController extends AbstractPlanController
{
    #[Route('', name: '', methods: ['GET'])]
    public function index(): Response
    {
        $this->entitlements->require($this->user(), 'plan_history');
        $versions = $this->user()->getPlanHistories()->toArray();
        usort($versions, fn ($a, $b) => $b->getCreatedAt() <=> $a->getCreatedAt());

        return $this->render('plan_de_vie/history/index.html.twig', ['versions' => $versions]);
    }

    #[Route('/enregistrer', name: '_save', methods: ['POST'])]
    public function save(Request $request, PlanSnapshotService $snapshots): Response
    {
        $this->entitlements->require($this->user(), 'plan_history');
        if ($this->isCsrfTokenValid('plan_history', $request->request->getString('_csrf_token'))) {
            $label = trim($request->request->getString('title'));
            $snapshots->snapshot($this->user(), $label ?: 'Version du '.date('d/m/Y'));
            $this->addFlash('success', 'Version enregistrée.');
        }

        return $this->redirectToRoute('app_plan_history');
    }

    #[Route('/{id}', name: '_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(PlanHistory $version): Response
    {
        $this->entitlements->require($this->user(), 'plan_history');
        if ($version->getUser() !== $this->user()) {
            throw $this->createNotFoundException();
        }

        return $this->render('plan_de_vie/history/show.html.twig', [
            'version' => $version,
            'data' => json_decode((string) $version->getData(), true) ?: [],
        ]);
    }
}
