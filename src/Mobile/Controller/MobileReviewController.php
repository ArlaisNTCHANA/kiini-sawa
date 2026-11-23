<?php

namespace App\Mobile\Controller;

use App\Abonnement\Service\Entitlements;
use App\Bilan\Entity\MonthlyReview;
use App\Bilan\Entity\WeeklyReview;
use App\Bilan\Repository\MonthlyReviewRepository;
use App\Bilan\Repository\WeeklyReviewRepository;
use App\Bilan\Service\Rhythms;
use App\Bilan\Service\WeeklyReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class MobileReviewController extends AbstractController
{
    use MobileJsonTrait;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WeeklyReviewService $weeklyService,
        private readonly WeeklyReviewRepository $weeklyRepo,
        private readonly MonthlyReviewRepository $monthlyRepo,
        private readonly Rhythms $rhythms,
        private readonly Entitlements $entitlements,
        private readonly \App\Social\Service\AccountabilityService $accountability,
    ) {
    }

    #[Route('/api/mobile/v1/revues/rythmes', name: 'api_mobile_v1_reviews_rhythms', methods: ['GET'])]
    public function rhythms(): JsonResponse
    {
        $user = $this->currentMobileUser();

        return $this->jsonNoStore([
            'rhythms' => $this->rhythms->forUser($user),
        ]);
    }

    #[Route('/api/mobile/v1/revues/hebdo', name: 'api_mobile_v1_reviews_weekly_get', methods: ['GET'])]
    public function getWeekly(): JsonResponse
    {
        $user = $this->currentMobileUser();
        $weekStart = $this->weeklyService->currentWeekStart();
        $existing = $this->weeklyRepo->findForWeek($user, $weekStart);

        $rawItems = $this->weeklyService->items($user);
        $cleanItems = [];
        foreach ($rawItems as $plan => $items) {
            $cleanItems[$plan] = array_map(function ($it) {
                return [
                    'type' => $it['type'],
                    'id' => $it['id'],
                    'key' => $it['type'].'_'.$it['id'],
                    'title' => $it['title'],
                    'progress' => $it['progress'],
                ];
            }, $items);
        }

        $recent = array_map(fn (WeeklyReview $w) => [
            'id' => $w->getId(),
            'weekStart' => $w->getWeekStart()->format('Y-m-d'),
            'completedAt' => $w->getCompletedAt()->format(\DateTimeInterface::ATOM),
            'wins' => $w->getWins(),
            'priorities' => $w->getPriorities(),
        ], $this->weeklyRepo->findRecent($user, 4));

        return $this->jsonNoStore([
            'weekStart' => $weekStart->format('Y-m-d'),
            'isCompleted' => null !== $existing,
            'review' => $existing ? [
                'id' => $existing->getId(),
                'completedAt' => $existing->getCompletedAt()->format(\DateTimeInterface::ATOM),
                'wins' => $existing->getWins(),
                'struggles' => $existing->getStruggles(),
                'lesson' => $existing->getLesson(),
                'priorities' => $existing->getPriorities(),
                'items' => $existing->getItems(),
            ] : null,
            'plans' => WeeklyReviewService::PLANS,
            'itemsByPlan' => $cleanItems,
            'habitsRegularity' => $this->weeklyService->habitsThisWeek($user),
            'journalEntriesCount' => $this->weeklyService->journalEntriesThisWeek($user),
            'recentReviews' => $recent,
            'accountability' => $this->accountability->weeklySummary($user),
        ]);
    }

    #[Route('/api/mobile/v1/revues/hebdo', name: 'api_mobile_v1_reviews_weekly_post', methods: ['POST'])]
    public function postWeekly(Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        $payload = $this->decodeJson($request);

        if (null === $payload) {
            return $this->jsonError('Corps JSON attendu.', Response::HTTP_BAD_REQUEST, 'INVALID_JSON');
        }

        $input = new InputBag($payload);
        $review = $this->weeklyService->save($user, $input);

        return $this->jsonNoStore([
            'success' => true,
            'review' => [
                'id' => $review->getId(),
                'weekStart' => $review->getWeekStart()->format('Y-m-d'),
                'completedAt' => $review->getCompletedAt()->format(\DateTimeInterface::ATOM),
                'wins' => $review->getWins(),
                'struggles' => $review->getStruggles(),
                'lesson' => $review->getLesson(),
                'priorities' => $review->getPriorities(),
                'items' => $review->getItems(),
            ],
        ]);
    }

    #[Route('/api/mobile/v1/revues/mensuelle', name: 'api_mobile_v1_reviews_monthly_get', methods: ['GET'])]
    public function getMonthly(): JsonResponse
    {
        $user = $this->currentMobileUser();
        $canAccess = $this->entitlements->can($user, 'monthly_review');

        if (!$canAccess) {
            return $this->jsonNoStore([
                'isProRequired' => true,
                'message' => 'La revue mensuelle fait partie des fonctionnalités réservées aux membres KIINI SAWA Pro.',
            ]);
        }

        $month = new \DateTimeImmutable('first day of this month');
        $existing = $this->monthlyRepo->findOneBy([
            'user' => $user,
            'month' => \DateTime::createFromImmutable($month),
        ]);

        return $this->jsonNoStore([
            'isProRequired' => false,
            'month' => $month->format('Y-m'),
            'isCompleted' => null !== $existing,
            'review' => $existing ? [
                'id' => $existing->getId(),
                'progress' => $existing->getProgress(),
                'stagnation' => $existing->getStagnation(),
                'blocker' => $existing->getBlocker(),
                'adjustment' => $existing->getAdjustment(),
                'decision' => $existing->getDecision(),
            ] : null,
        ]);
    }

    #[Route('/api/mobile/v1/revues/mensuelle', name: 'api_mobile_v1_reviews_monthly_post', methods: ['POST'])]
    public function postMonthly(Request $request): JsonResponse
    {
        $user = $this->currentMobileUser();
        if (!$this->entitlements->can($user, 'monthly_review')) {
            return $this->jsonError('Abonnement requis pour la revue mensuelle.', Response::HTTP_FORBIDDEN, 'PRO_REQUIRED');
        }

        $payload = $this->decodeJson($request);
        if (null === $payload) {
            return $this->jsonError('Corps JSON attendu.', Response::HTTP_BAD_REQUEST, 'INVALID_JSON');
        }

        $month = new \DateTimeImmutable('first day of this month');
        $review = $this->monthlyRepo->findOneBy([
            'user' => $user,
            'month' => \DateTime::createFromImmutable($month),
        ]) ?? (new MonthlyReview())->setUser($user)->setMonth(\DateTime::createFromImmutable($month));

        $fields = ['progress', 'stagnation', 'blocker', 'adjustment', 'decision'];
        foreach ($fields as $field) {
            if (\array_key_exists($field, $payload)) {
                $val = trim((string) $payload[$field]);
                $review->{'set'.ucfirst($field)}('' === $val ? null : mb_substr($val, 0, 5000));
            }
        }

        $this->em->persist($review);
        $this->em->flush();

        return $this->jsonNoStore([
            'success' => true,
            'review' => [
                'id' => $review->getId(),
                'month' => $month->format('Y-m'),
                'progress' => $review->getProgress(),
                'stagnation' => $review->getStagnation(),
                'blocker' => $review->getBlocker(),
                'adjustment' => $review->getAdjustment(),
                'decision' => $review->getDecision(),
            ],
        ]);
    }
}
