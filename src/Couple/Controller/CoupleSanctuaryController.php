<?php

namespace App\Couple\Controller;

use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleItem;
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
        private readonly \App\Couple\Service\CoupleSanctuaryTexts $texts,
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
            'dailyVerse' => $this->texts->dailyVerse(),
            'meditation' => $active ? $this->sanctuary->serializeMeditation($active, $this->user()) : null,
            'catalog' => $this->texts->meditations(),
            'intentions' => $couple->itemsOfKind('INTENTION'),
        ] + $this->retreatView($couple));
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
            'catalog' => $this->texts->challenges(),
            'themes' => CoupleService::THEMES,
        ] + $this->retreatView($couple));
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

    // ── Temps de retraite ──────────────────────────────────────

    #[Route('/couple/retraite/proposer', name: 'app_couple_retreat_propose', methods: ['POST'])]
    public function proposeRetreat(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('startsOn'));
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $request->request->getString('endsOn'));
        if (!$start || !$end) {
            $this->addFlash('error', 'Indiquez une date de début et une date de fin.');
        } else {
            try {
                $this->sanctuary->proposeRetreat($couple, $this->user(), $start, $end, $request->request->getString('note'));
                $partner = $couple->partnerOf($this->user());
                $this->addFlash('success', 'Proposition envoyée. La retraite comptera dès que '.($partner ? $partner->getDisplayName() : 'votre conjoint').' l\'aura confirmée.');
            } catch (\InvalidArgumentException|\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectBack($request);
    }

    #[Route('/couple/retraite/confirmer', name: 'app_couple_retreat_confirm', methods: ['POST'])]
    public function confirmRetreat(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        try {
            $this->sanctuary->confirmRetreat($couple, $this->user());
            $this->addFlash('success', 'Temps de retraite confirmé. Pendant ces dates, vous pouvez vivre plusieurs jours de défi et plusieurs étapes de méditation le même jour.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectBack($request);
    }

    #[Route('/couple/retraite/arreter', name: 'app_couple_retreat_stop', methods: ['POST'])]
    public function stopRetreat(Request $request): Response
    {
        $couple = $this->postedCouple($request);
        try {
            $this->sanctuary->stopRetreat($couple, $this->user());
            $this->addFlash('success', 'Retraite arrêtée. Le rythme d\'un jour à la fois reprend.');
        } catch (\DomainException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectBack($request);
    }

    /** Retour vers la page d'où vient le formulaire (Le Jardin des défis ou L'Autel). */
    private function redirectBack(Request $request): Response
    {
        return 'autel' === $request->request->getString('retour')
            ? $this->redirectToRoute('app_couple_altar', ['_fragment' => 'retraite'])
            : $this->redirectToRoute('app_couple_challenges', ['_fragment' => 'retraite']);
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

    /** @return array{retreat: array<string, mixed>|null, pastRetreats: list<array{retreat: \App\Couple\Entity\CoupleRetreat, days: int}>} */
    private function retreatView(Couple $couple): array
    {
        return [
            'retreat' => $this->sanctuary->serializeRetreat($this->sanctuary->currentRetreat($couple), $this->user()),
            'pastRetreats' => array_map(fn ($r) => ['retreat' => $r, 'days' => $this->sanctuary->challengeDaysDuring($r)], $this->sanctuary->pastRetreats($couple)),
        ];
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
