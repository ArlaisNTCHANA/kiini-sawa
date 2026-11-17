<?php

namespace App\Personnalite\Controller;

use App\Abonnement\Exception\ProRequiredException;
use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Personnalite\Entity\TestAction;
use App\Personnalite\Repository\TestActionRepository;
use App\Personnalite\Service\ProfileSynthesis;
use App\Personnalite\Service\TestActionService;
use App\Shared\Service\PdfFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * « Mon profil KIINI SAWA » (synthèse croisée des tests) et ajout des actions des rapports aux plans.
 */
#[Route('/mes-tests')]
#[IsGranted('ROLE_USER')]
final class SynthesisController extends AbstractController
{
    public function __construct(
        private readonly ProfileSynthesis $synthesis,
        private readonly Entitlements $entitlements,
        private readonly TestActionRepository $actions,
    ) {
    }

    #[Route('/synthese', name: 'app_synthesis', methods: ['GET'])]
    public function show(): Response
    {
        $user = $this->user();
        $data = $this->synthesis->build($user);

        return $this->render('personnalite/synthesis.html.twig', $data + [
            'locked' => !$this->entitlements->isPro($user), 'addedActions' => $this->actions->addedBy($user),
        ]);
    }

    #[Route('/synthese/pdf', name: 'app_synthesis_pdf', methods: ['GET'])]
    public function pdf(PdfFactory $pdf): Response
    {
        $user = $this->user();
        if (!$this->entitlements->isPro($user)) {
            throw new ProRequiredException('Le rapport « Mon profil KIINI SAWA » est réservé à l\'offre PRO.');
        }
        $data = $this->synthesis->build($user);
        if (!$data['enough']) {
            return $this->redirectToRoute('app_synthesis');
        }
        $html = $this->renderView('personnalite/assessment/report/pdf.html.twig', $data + [
            'body' => 'personnalite/assessment/report/synthesis.html.twig', 'locked' => false, 'name' => $user->getDisplayName(),
            'instrument' => ['name' => 'Mon profil KIINI SAWA'],
        ]);

        return new Response($pdf->render($html), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'kiini-sawa-mon-profil-'.date('Y-m-d').'.pdf'),
        ]);
    }

    /** Ajoute une action d'un rapport aux rituels ou au Plan de Croissance. */
    #[Route('/action', name: 'app_test_action_add', methods: ['POST'])]
    public function addAction(Request $request, TestActionService $service): Response
    {
        $back = $request->request->getString('back');
        $back = str_starts_with($back, '/mes-tests') ? $back : $this->generateUrl('app_personality_index');
        if (!$this->isCsrfTokenValid('test_action', $request->request->getString('_csrf_token'))) {
            $this->addFlash('error', 'La page a expiré : merci de réessayer.');

            return $this->redirect($back);
        }
        $target = $request->request->getString('target');
        try {
            $service->add($this->user(), $request->request->getString('text'), $request->request->getString('source'), $target);
            $this->addFlash('success', sprintf('Ajouté à %s. Nous vous demanderons dans 30 jours où vous en êtes.', TestAction::TARGETS[$target]));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($back);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
