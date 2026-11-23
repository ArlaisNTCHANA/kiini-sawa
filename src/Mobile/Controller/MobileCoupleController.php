<?php

namespace App\Mobile\Controller;

use App\Couple\Entity\Couple;
use App\Couple\Entity\CoupleItem;
use App\Couple\Entity\CoupleReview;
use App\Couple\Service\CoupleSanctuaryCatalog;
use App\Couple\Service\CoupleSanctuaryService;
use App\Couple\Service\CoupleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API mobile du Sanctuaire du Couple 360 (RFC-001).
 */
#[IsGranted('ROLE_USER')]
#[Route('/api/mobile/v1/couple')]
final class MobileCoupleController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly CoupleService $coupleService,
        private readonly CoupleSanctuaryService $sanctuary,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Vérifie que l'utilisateur appartient à un couple actif et autorisé (les deux conjoints PRO / Duo).
     * Retourne soit le Couple, soit une JsonResponse d'erreur.
     */
    private function requireUsableCouple(): Couple|JsonResponse
    {
        $user = $this->currentMobileUser();
        $couple = $this->coupleService->coupleOf($user);

        if (null === $couple) {
            return $this->jsonError('Aucun espace couple associé à votre compte.', Response::HTTP_FORBIDDEN, 'no_couple');
        }
        if (!$couple->isActive()) {
            return $this->jsonError('En attente que votre conjoint rejoigne votre espace couple.', Response::HTTP_FORBIDDEN, 'couple_pending');
        }
        if (!$this->coupleService->canUse($couple)) {
            return $this->jsonError('L\'espace couple nécessite que les deux conjoints soient PRO (ou abonnés Duo).', Response::HTTP_FORBIDDEN, 'couple_locked');
        }

        return $couple;
    }

    #[Route('/home', name: 'api_mobile_v1_couple_home', methods: ['GET'])]
    public function home(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        return $this->jsonNoStore($this->sanctuary->homeData($couple, $this->currentMobileUser()));
    }

    #[Route('/projects', name: 'api_mobile_v1_couple_projects_list', methods: ['GET'])]
    public function listProjects(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $items = array_map(
            fn (CoupleItem $i) => $this->sanctuary->serializeItem($i),
            $couple->itemsOfKind('PROJECT')
        );

        return $this->jsonNoStore([
            'categories' => CoupleService::PROJECT_CATEGORIES,
            'projects' => $items,
        ]);
    }

    #[Route('/projects', name: 'api_mobile_v1_couple_projects_create', methods: ['POST'])]
    public function createProject(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        try {
            $item = $this->coupleService->addItem($couple, $this->currentMobileUser(), 'PROJECT', [
                'title' => (string) ($body['title'] ?? ''),
                'category' => (string) ($body['category'] ?? 'DO'),
                'deadline' => $body['deadline'] ?? null,
                'details' => $body['details'] ?? null,
                'progress' => $body['progress'] ?? 0,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonNoStore(['project' => $this->sanctuary->serializeItem($item)], Response::HTTP_CREATED);
    }

    #[Route('/projects/{id}', name: 'api_mobile_v1_couple_projects_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateProject(int $id, Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $item = $this->em->getRepository(CoupleItem::class)->find($id);
        if (!$item || $item->getCouple() !== $couple || 'PROJECT' !== $item->getKind()) {
            return $this->jsonError('Projet introuvable.', Response::HTTP_NOT_FOUND);
        }

        $body = $this->decodeJson($request) ?? [];
        $this->coupleService->updateItem($item, $body);

        return $this->jsonNoStore(['project' => $this->sanctuary->serializeItem($item)]);
    }

    #[Route('/projects/{id}', name: 'api_mobile_v1_couple_projects_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteProject(int $id): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $item = $this->em->getRepository(CoupleItem::class)->find($id);
        if (!$item || $item->getCouple() !== $couple || 'PROJECT' !== $item->getKind()) {
            return $this->jsonError('Projet introuvable.', Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->jsonNoStore(['deleted' => true]);
    }

    #[Route('/intentions', name: 'api_mobile_v1_couple_intentions_list', methods: ['GET'])]
    public function listIntentions(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $items = array_map(
            fn (CoupleItem $i) => $this->sanctuary->serializeItem($i),
            $couple->itemsOfKind('INTENTION')
        );

        return $this->jsonNoStore(['intentions' => $items]);
    }

    #[Route('/intentions', name: 'api_mobile_v1_couple_intentions_create', methods: ['POST'])]
    public function createIntention(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        try {
            $item = $this->coupleService->addItem($couple, $this->currentMobileUser(), 'INTENTION', [
                'title' => (string) ($body['title'] ?? ''),
                'details' => $body['details'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonNoStore(['intention' => $this->sanctuary->serializeItem($item)], Response::HTTP_CREATED);
    }

    #[Route('/intentions/{id}', name: 'api_mobile_v1_couple_intentions_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function deleteIntention(int $id): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $item = $this->em->getRepository(CoupleItem::class)->find($id);
        if (!$item || $item->getCouple() !== $couple || 'INTENTION' !== $item->getKind()) {
            return $this->jsonError('Intention introuvable.', Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($item);
        $this->em->flush();

        return $this->jsonNoStore(['deleted' => true]);
    }

    #[Route('/challenges/catalog', name: 'api_mobile_v1_couple_challenges_catalog', methods: ['GET'])]
    public function challengesCatalog(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $active = $this->sanctuary->activeChallenge($couple);
        $catalog = array_values(array_map(
            static fn (array $c) => [
                'code' => $c['code'],
                'title' => $c['title'],
                'subtitle' => $c['subtitle'],
                'theme' => $c['theme'],
                'durationDays' => $c['durationDays'],
            ],
            CoupleSanctuaryCatalog::CHALLENGES
        ));

        return $this->jsonNoStore([
            'active' => $active ? $this->sanctuary->serializeChallenge($active, $this->currentMobileUser()) : null,
            'catalog' => $catalog,
        ]);
    }

    #[Route('/challenges/start', name: 'api_mobile_v1_couple_challenges_start', methods: ['POST'])]
    public function startChallenge(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        $code = trim((string) ($body['code'] ?? ''));
        try {
            $challenge = $this->sanctuary->startChallenge($couple, $this->currentMobileUser(), $code);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonNoStore([
            'challenge' => $this->sanctuary->serializeChallenge($challenge, $this->currentMobileUser()),
        ], Response::HTTP_CREATED);
    }

    #[Route('/challenges/check-day', name: 'api_mobile_v1_couple_challenges_check_day', methods: ['POST'])]
    public function checkChallengeDay(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        $note = isset($body['note']) ? (string) $body['note'] : null;

        try {
            $challenge = $this->sanctuary->checkChallengeDay($couple, $this->currentMobileUser(), $note);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore([
            'streakDays' => $couple->getStreakDays(),
            'challenge' => $this->sanctuary->serializeChallenge($challenge, $this->currentMobileUser()),
        ]);
    }

    #[Route('/meditations/catalog', name: 'api_mobile_v1_couple_meditations_catalog', methods: ['GET'])]
    public function meditationsCatalog(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $active = $this->sanctuary->activeMeditation($couple);
        $catalog = array_values(array_map(
            static fn (array $m) => [
                'code' => $m['code'],
                'title' => $m['title'],
                'subtitle' => $m['subtitle'],
                'durationMinutes' => $m['durationMinutes'],
                'totalSteps' => \count($m['steps']),
            ],
            CoupleSanctuaryCatalog::MEDITATIONS
        ));

        return $this->jsonNoStore([
            'active' => $active ? $this->sanctuary->serializeMeditation($active, $this->currentMobileUser()) : null,
            'catalog' => $catalog,
        ]);
    }

    #[Route('/meditations/start', name: 'api_mobile_v1_couple_meditations_start', methods: ['POST'])]
    public function startMeditation(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        $code = trim((string) ($body['code'] ?? ''));
        try {
            $meditation = $this->sanctuary->startMeditation($couple, $this->currentMobileUser(), $code);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->jsonNoStore([
            'meditation' => $this->sanctuary->serializeMeditation($meditation, $this->currentMobileUser()),
        ], Response::HTTP_CREATED);
    }

    #[Route('/meditations/complete-step', name: 'api_mobile_v1_couple_meditations_complete_step', methods: ['POST'])]
    public function completeMeditationStep(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        try {
            $meditation = $this->sanctuary->completeMeditationStep($couple, $this->currentMobileUser());
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore([
            'streakDays' => $couple->getStreakDays(),
            'meditation' => $this->sanctuary->serializeMeditation($meditation, $this->currentMobileUser()),
        ]);
    }

    #[Route('/ping', name: 'api_mobile_v1_couple_ping', methods: ['POST'])]
    public function ping(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        $preset = trim((string) ($body['preset'] ?? 'pensee'));
        $message = isset($body['message']) ? (string) $body['message'] : null;

        try {
            $this->sanctuary->sendPing($couple, $this->currentMobileUser(), $preset, $message);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore(['sent' => true]);
    }

    #[Route('/retreat', name: 'api_mobile_v1_couple_retreat_get', methods: ['GET'])]
    public function getRetreat(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        return $this->jsonNoStore(['retreat' => $this->sanctuary->serializeRetreat($this->sanctuary->currentRetreat($couple), $this->currentMobileUser())]);
    }

    /** Proposer un temps de retraite : { startsOn: "AAAA-MM-JJ", endsOn: "AAAA-MM-JJ", note? }. Compte une fois confirmé par l'autre. */
    #[Route('/retreat/propose', name: 'api_mobile_v1_couple_retreat_propose', methods: ['POST'])]
    public function proposeRetreat(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($body['startsOn'] ?? ''));
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($body['endsOn'] ?? ''));
        if (!$start || !$end) {
            return $this->jsonError('Indiquez une date de début et une date de fin.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        try {
            $retreat = $this->sanctuary->proposeRetreat($couple, $this->currentMobileUser(), $start, $end, isset($body['note']) ? (string) $body['note'] : null);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore(['retreat' => $this->sanctuary->serializeRetreat($retreat, $this->currentMobileUser())], Response::HTTP_CREATED);
    }

    #[Route('/retreat/confirm', name: 'api_mobile_v1_couple_retreat_confirm', methods: ['POST'])]
    public function confirmRetreat(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }
        try {
            $retreat = $this->sanctuary->confirmRetreat($couple, $this->currentMobileUser());
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore(['retreat' => $this->sanctuary->serializeRetreat($retreat, $this->currentMobileUser())]);
    }

    #[Route('/retreat/stop', name: 'api_mobile_v1_couple_retreat_stop', methods: ['POST'])]
    public function stopRetreat(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }
        try {
            $this->sanctuary->stopRetreat($couple, $this->currentMobileUser());
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_CONFLICT);
        }

        return $this->jsonNoStore(['retreat' => null]);
    }

    #[Route('/review', name: 'api_mobile_v1_couple_review_get', methods: ['GET'])]
    public function getReview(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $month = new \DateTimeImmutable('first day of this month midnight');
        $current = $this->coupleService->reviewFor($couple, $month);
        $history = array_map(
            static fn (CoupleReview $r) => [
                'id' => $r->getId(),
                'month' => $r->getMonth()->format('Y-m'),
                'answers' => $r->getAnswers(),
                'updatedAt' => $r->getUpdatedAt()->format('Y-m-d'),
            ],
            \array_slice($this->coupleService->reviews($couple), 0, 6)
        );

        $questions = [];
        foreach (CoupleReview::QUESTIONS as $key => [$label, $hint]) {
            $questions[] = ['key' => $key, 'label' => $label, 'hint' => $hint];
        }

        return $this->jsonNoStore([
            'currentMonth' => $month->format('Y-m'),
            'questions' => $questions,
            'currentAnswers' => $current?->getAnswers() ?? [],
            'history' => $history,
        ]);
    }

    #[Route('/review', name: 'api_mobile_v1_couple_review_save', methods: ['POST'])]
    public function saveReview(Request $request): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $body = $this->decodeJson($request) ?? [];
        $answers = \is_array($body['answers'] ?? null) ? $body['answers'] : [];

        $review = $this->coupleService->saveReview($couple, $this->currentMobileUser(), $answers);
        $couple->recordActivity();
        $this->em->flush();

        return $this->jsonNoStore([
            'saved' => true,
            'streakDays' => $couple->getStreakDays(),
            'review' => [
                'id' => $review->getId(),
                'month' => $review->getMonth()->format('Y-m'),
                'answers' => $review->getAnswers(),
                'updatedAt' => $review->getUpdatedAt()->format('Y-m-d'),
            ],
        ]);
    }

    #[Route('/report', name: 'api_mobile_v1_couple_report', methods: ['GET'])]
    public function getReport(): JsonResponse
    {
        $couple = $this->requireUsableCouple();
        if ($couple instanceof JsonResponse) {
            return $couple;
        }

        $raw = $this->coupleService->crossReport($couple);

        return $this->jsonNoStore([
            'partnerA' => [
                'name' => $raw['people'][0]['user']->getDisplayName(),
                'sharesTests' => $raw['people'][0]['shares'],
                'hasProfile' => null !== $raw['people'][0]['profile'],
            ],
            'partnerB' => [
                'name' => $raw['people'][1]['user']->getDisplayName(),
                'sharesTests' => $raw['people'][1]['shares'],
                'hasProfile' => null !== $raw['people'][1]['profile'],
            ],
            'domains' => array_values($raw['domains']),
            'styles' => $raw['styles'],
            'types' => $raw['types'],
            'enneagram' => $raw['enneagram'],
        ]);
    }
}
