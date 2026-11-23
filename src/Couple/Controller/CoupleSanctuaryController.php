<?php

namespace App\Couple\Controller;

use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleItem;
use App\Couple\Service\CoupleSanctuaryCatalog;
use App\Couple\Service\CoupleSanctuaryService;
use App\Couple\Service\CoupleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Le Sanctuaire du Couple sur le web (RFC-001) : L'Autel (méditations à deux, intentions de prière),
 * Le Jardin des défis et les signaux envoyés au conjoint. Mêmes règles et même service que l'application mobile.
 */
class CoupleSanctuaryController extends AbstractController
{
    public function __construct(
        private readonly CoupleService $couples,
        private readonly CoupleSanctuaryService $sanctuary,
        private readonly EntityManagerInterface $em,
    ) {
    }

    // ── L'Autel ────────────────────────────────────────────────

    #[Route('/couple/autel', name: 'app_couple_altar', methods: ['GET'])]
    public function altar(): Response
    {
        $couple = $this->usableCouple();
        if (null === $couple) {
            return $this->redirectToRoute('app_couple');
        }
        $active = $this->sanctuary->activeMeditation($couple);

        return $this->render('couple/sanctuary/altar.html.twig', [
            'couple' => $couple,
            'partner' => $couple->partnerOf($this->user()),
            'dailyVerse' => CoupleSanctuaryCatalog::dailyVerse(),
            'meditation' => $active ? $this->sanctuary->serializeMeditation($active) : null,
            'catalog' => CoupleSanctuaryCatalog::MEDITATIONS,
            'intentions' => $couple->itemsOfKind('INTENTION'),
        ]);
    }

    #[Route('/couple/autel/commencer', name: 'app_couple_altar_start', methods: ['POST'])]
    public function startMeditation(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        try {
            $this->sanctuary->startMeditation($couple, $this->user(), $request->request->getString('code'));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_couple_altar', ['_fragment' => 'meditation']);
    }

    #[Route('/couple/autel/etape', name: 'app_couple_altar_step', methods: ['POST'])]
    public function completeStep(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        try {
            $meditation = $this->sanctuary->completeMeditationStep($couple, $this->user());
            if (null !== $meditation->getCompletedAt()) {
                $this->addFlash('success', 'Parcours terminé. Que cette parole continue de porter votre foyer.');
            }
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_couple_altar', ['_fragment' => 'meditation']);
    }

    #[Route('/couple/autel/intentions', name: 'app_couple_intention_add', methods: ['POST'])]
    public function addIntention(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        try {
            $this->couples->addItem($couple, $this->user(), 'INTENTION', [
                'title' => $request->request->getString('title'),
                'details' => $request->request->getString('details'),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_couple_altar', ['_fragment' => 'intentions']);
    }

    #[Route('/couple/autel/intentions/{id}/retirer', name: 'app_couple_intention_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteIntention(int $id, Request $request): Response
    {
        $couple = $this->postedCouple($request);
        $item = $this->em->getRepository(CoupleItem::class)->find($id);
        if (!$item instanceof CoupleItem || $item->getCouple() !== $couple || 'INTENTION' !== $item->getKind()) {
            throw $this->createNotFoundException();
        }
        $this->em->remove($item);
        $this->em->flush();

        return $this->redirectToRoute('app_couple_altar', ['_fragment' => 'intentions']);
    }

    // ── Le Jardin des défis ────────────────────────────────────

    #[Route('/couple/defis', name: 'app_couple_challenges', methods: ['GET'])]
    public function challenges(): Response
    {
        $couple = $this->usableCouple();
        if (null === $couple) {
            return $this->redirectToRoute('app_couple');
        }
        $active = $this->sanctuary->activeChallenge($couple);

        return $this->render('couple/sanctuary/challenges.html.twig', [
            'couple' => $couple,
            'partner' => $couple->partnerOf($this->user()),
            'challenge' => $active ? $this->sanctuary->serializeChallenge($active, $this->user()) : null,
            'catalog' => CoupleSanctuaryCatalog::CHALLENGES,
            'themes' => CoupleService::THEMES,
        ]);
    }

    #[Route('/couple/defis/commencer', name: 'app_couple_challenge_start', methods: ['POST'])]
    public function startChallenge(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        try {
            $this->sanctuary->startChallenge($couple, $this->user(), $request->request->getString('code'));
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_couple_challenges');
    }

    #[Route('/couple/defis/valider', name: 'app_couple_challenge_check', methods: ['POST'])]
    public function checkDay(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        $note = trim($request->request->getString('note'));
        try {
            $challenge = $this->sanctuary->checkChallengeDay($couple, $this->user(), '' !== $note ? mb_substr($note, 0, 500) : null);
            if (null !== $challenge->getCompletedAt()) {
                $this->addFlash('success', 'Défi terminé ensemble. Bravo à vous deux !');
            }
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_couple_challenges');
    }

    // ── Signaux ────────────────────────────────────────────────

    #[Route('/couple/signal', name: 'app_couple_signal', methods: ['POST'])]
    public function signal(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        try {
            $message = trim($request->request->getString('message'));
            $this->sanctuary->sendPing($couple, $this->user(), $request->request->getString('preset'), '' !== $message ? $message : null);
            $partner = $couple->partnerOf($this->user());
            $this->addFlash('success', 'Votre signal a été envoyé'.($partner ? ' à '.$partner->getDisplayName() : '').'.');
        } catch (\InvalidArgumentException|\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_couple', ['_fragment' => 'sanctuaire']);
    }

    private function usableCouple(): ?Couple
    {
        $couple = $this->couples->coupleOf($this->user());

        return $couple && $this->couples->canUse($couple) ? $couple : null;
    }

    /** Pour les envois de formulaire : couple utilisable et jeton CSRF valide, sinon accès refusé. */
    private function postedCouple(Request $request): Couple
    {
        $couple = $this->usableCouple();
        if (null === $couple || !$this->isCsrfTokenValid('couple', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException();
        }

        return $couple;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user;
    }
}
