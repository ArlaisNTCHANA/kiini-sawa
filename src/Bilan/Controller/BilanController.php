<?php

namespace App\Bilan\Controller;

use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\GrowthGoalScore;
use App\Defis\Entity\ChallengeParticipant;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BilanController extends AbstractController
{
    #[Route('/bilan', name: 'app_bilan')]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $planDeCroissance = $user->getPlanDeCroissance();

        $goals = [];
        if ($planDeCroissance) {
            $goals = $em->getRepository(GrowthGoal::class)->createQueryBuilder('g')
                ->leftJoin('g.habits', 'h')
                ->leftJoin('h.logs', 'hl')
                ->leftJoin('g.scores', 's')
                ->addSelect('h', 'hl', 's')
                ->where('g.planDeCroissance = :plan')
                ->setParameter('plan', $planDeCroissance)
                ->orderBy('s.date', 'ASC')
                ->getQuery()
                ->getResult();
        }

        // Fetch active challenges to pass to the template for the wizard catch-up
        $challenges = $em->getRepository(ChallengeParticipant::class)->createQueryBuilder('cp')
            ->join('cp.challenge', 'c')
            ->leftJoin('cp.logs', 'cl')
            ->addSelect('c', 'cl')
            ->where('cp.user = :user')
            ->andWhere('cp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getResult();

        // Convert goals to simple array for JSON representation in Alpine.js
        $goalsJson = [];
        /** @var GrowthGoal $goal */
        foreach ($goals as $goal) {
            $habits = [];
            foreach ($goal->getHabits() as $h) {
                $logs = [];
                foreach ($h->getLogs() as $l) {
                    $logs[] = [
                        'date' => $l->getDate()->format('Y-m-d'),
                        'status' => $l->isStatus(),
                    ];
                }
                $habits[] = [
                    'id' => $h->getId(),
                    'title' => $h->getTitle(),
                    'frequency' => $h->getFrequency()->value,
                    'endDate' => $h->getEndDate() ? $h->getEndDate()->format('Y-m-d') : null,
                    'recurrenceDay' => $h->getRecurrenceDay(),
                    'recurrenceWeek' => $h->getRecurrenceWeek(),
                    'logs' => $logs,
                ];
            }

            $scores = [];
            foreach ($goal->getScores() as $s) {
                $scores[] = [
                    'id' => $s->getId(),
                    'date' => $s->getDate()->format('Y-m-d'),
                    'score' => $s->getScore(),
                    'progressValue' => $s->getProgressValue(),
                    'note' => $s->getNote(),
                ];
            }

            $sourceTitle = 'OBJECTIF DE CROISSANCE';
            if ($goal->getSourceType() && $goal->getSourceId()) {
                if ($goal->getSourceType() === 'LIMITING_BELIEF' || $goal->getSourceType() === 'CONVICTION') {
                    $entity = $em->getRepository(\App\PlanDeVie\Entity\InternalBalance::class)->find($goal->getSourceId());
                    if ($entity) {
                        $sourceTitle = $entity->getText();
                    }
                } elseif ($goal->getSourceType() === 'COUNTER_VALUE' || $goal->getSourceType() === 'DECLARED_VALUE') {
                    $entity = $em->getRepository(\App\PlanDeVie\Entity\Value::class)->find($goal->getSourceId());
                    if ($entity) {
                        $sourceTitle = $entity->getName();
                    }
                }
            }

            $goalsJson[] = [
                'id' => $goal->getId(),
                'title' => $goal->getTitle(),
                'axis' => $goal->getAxis()->value, // CHARACTER, RELATION, COMPETENCE
                'sourceTitle' => $sourceTitle,
                'strategy' => $goal->getStrategy(),
                'metric' => $goal->getMetric(),
                'progress' => $goal->getProgress(),
                'deadline' => $goal->getDeadline() ? $goal->getDeadline()->format('Y-m-d') : null,
                'habits' => $habits,
                'scores' => $scores,
            ];
        }

        $challengesJson = [];
        /** @var ChallengeParticipant $part */
        foreach ($challenges as $part) {
            $challenge = $part->getChallenge();
            
            $logs = [];
            foreach ($part->getLogs() as $cl) {
                $logs[] = [
                    'date' => $cl->getDate()->format('Y-m-d'),
                    'status' => $cl->isStatus(),
                ];
            }

            $challengesJson[] = [
                'id' => $part->getId(), // participation ID
                'title' => $challenge->getTitle(),
                'description' => $challenge->getDescription(),
                'startDate' => $challenge->getStartDate()->format('Y-m-d'),
                'endDate' => $challenge->getEndDate() ? $challenge->getEndDate()->format('Y-m-d') : null,
                'frequency' => $challenge->getFrequency(),
                'recurrenceDay' => $challenge->getRecurrenceDay(),
                'recurrenceDays' => $challenge->getRecurrenceDays(),
                'recurrenceWeek' => $challenge->getRecurrenceWeek(),
                'recurrenceOrdinal' => $challenge->getRecurrenceOrdinal(),
                'linkedGoalId' => $challenge->getHabitTitle(), // wait, we can link by goal or habit
                'logs' => $logs,
            ];
        }

        // Get internal balances to check limiting beliefs
        $hasLimitingBeliefs = false;
        $limitingBeliefs = [];
        foreach ($user->getInternalBalances() as $ib) {
            if ($ib->getType() === 'LIMITING_BELIEF') {
                $hasLimitingBeliefs = true;
                $limitingBeliefs[] = $ib->getText();
            }
        }

        return $this->render('bilan/index.html.twig', [
            'goals' => $goals,
            'goalsJson' => $goalsJson,
            'challengesJson' => $challengesJson,
            'hasLimitingBeliefs' => $hasLimitingBeliefs,
            'limitingBeliefs' => $limitingBeliefs,
        ]);
    }

    #[Route('/bilan/rythmes', name: 'app_rhythms')]
    public function rhythms(\App\Bilan\Service\Rhythms $rhythms): Response
    {
        return $this->render('bilan/rhythms.html.twig', ['rhythms' => $rhythms->forUser($this->getUser())]);
    }

    #[Route('/api/bilan/save-score', name: 'api_bilan_save_score', methods: ['POST'])]
    public function saveScore(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['goalId']) || !isset($data['score'])) {
            return new JsonResponse(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        $goal = $em->getRepository(GrowthGoal::class)->find((int)$data['goalId']);
        if (!$goal || $goal->getPlanDeCroissance()->getUser() !== $user) {
            return new JsonResponse(['error' => 'Goal not found'], Response::HTTP_NOT_FOUND);
        }

        // Create new score
        $score = new GrowthGoalScore();
        $score->setGrowthGoal($goal);
        $score->setScore((int)$data['score']);
        $score->setProgressValue((int)($data['progressValue'] ?? $goal->getProgress()));
        $score->setNote($data['note'] ?? null);
        $score->setDate(new \DateTime());
        $em->persist($score);

        // Update progress on the goal itself
        if (isset($data['progressValue'])) {
            $goal->setProgress((int)$data['progressValue']);
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
