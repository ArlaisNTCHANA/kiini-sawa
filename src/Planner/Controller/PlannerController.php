<?php

namespace App\Planner\Controller;

use App\Compte\Entity\User;
use App\Planner\Entity\PlannerTask;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeVie\Entity\LifeMilestone;
use App\Rituels\Entity\Habit;
use App\Rituels\Entity\HabitLog;
use App\PlanDHygiene\Entity\DailyLog;
use App\Defis\Entity\ChallengeParticipant;
use App\Defis\Entity\ChallengeLog;
use App\Defis\Entity\ChallengeActivity;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PlannerController extends AbstractController
{
    #[Route('/planner', name: 'app_planner')]
    public function index(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // Get growth goals to populate dropdown for linking tasks
        $growthGoals = [];
        if ($user) {
            $planDeCroissance = $user->getPlanDeCroissance();
            if ($planDeCroissance) {
                foreach ($planDeCroissance->getGrowthGoals() as $gg) {
                    $growthGoals[] = [
                        'id' => $gg->getId(),
                        'title' => $gg->getTitle(),
                    ];
                }
            }
        }

        return $this->render('planner/index.html.twig', [
            'growthGoals' => $growthGoals,
        ]);
    }

    #[Route('/api/planner/events', name: 'api_planner_events', methods: ['GET'])]
    public function getEvents(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');

        try {
            $startDate = $startStr ? new \DateTime($startStr) : new \DateTime('first day of this month');
            $endDate = $endStr ? new \DateTime($endStr) : new \DateTime('last day of this month');
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        // Set start time to 00:00:00 and end time to 23:59:59
        $startDate->setTime(0, 0, 0);
        $endDate->setTime(23, 59, 59);

        // 1. Fetch Manual Tasks
        $tasks = $em->getRepository(PlannerTask::class)->createQueryBuilder('t')
            ->leftJoin('t.growthGoal', 'gg')
            ->addSelect('gg')
            ->where('t.user = :user')
            ->andWhere('t.date >= :start')
            ->andWhere('t.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        // 2. Fetch Milestones
        $milestones = $em->getRepository(LifeMilestone::class)->createQueryBuilder('m')
            ->join('m.lifeGoal', 'lg')
            ->join('lg.planDeVie', 'pv')
            ->addSelect('lg')
            ->where('pv.user = :user')
            ->andWhere('m.dueDate >= :start')
            ->andWhere('m.dueDate <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        // 3. Fetch Habits & Logs
        $habits = $em->getRepository(Habit::class)->createQueryBuilder('h')
            ->leftJoin('h.growthGoal', 'gg')
            ->addSelect('gg')
            ->where('h.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $logs = $em->getRepository(HabitLog::class)->createQueryBuilder('l')
            ->join('l.habit', 'h')
            ->where('h.user = :user')
            ->andWhere('l.date >= :start')
            ->andWhere('l.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        $logsMap = [];
        /** @var HabitLog $log */
        foreach ($logs as $log) {
            $logsMap[$log->getHabit()->getId()][$log->getDate()->format('Y-m-d')] = $log->isStatus();
        }

        // 4. Fetch Challenge Participations & Logs
        $participations = $em->getRepository(ChallengeParticipant::class)->createQueryBuilder('cp')
            ->join('cp.challenge', 'c')
            ->addSelect('c')
            ->where('cp.user = :user')
            ->andWhere('cp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'ACTIVE')
            ->getQuery()
            ->getResult();

        $challengeLogs = $em->getRepository(ChallengeLog::class)->createQueryBuilder('cl')
            ->join('cl.participant', 'cp')
            ->where('cp.user = :user')
            ->andWhere('cl.date >= :start')
            ->andWhere('cl.date <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->getQuery()
            ->getResult();

        $challengeLogsMap = [];
        /** @var ChallengeLog $cl */
        foreach ($challengeLogs as $cl) {
            $challengeLogsMap[$cl->getParticipant()->getId()][$cl->getDate()->format('Y-m-d')] = $cl->isStatus();
        }

        $events = [];

        // Map Tasks
        /** @var PlannerTask $t */
        foreach ($tasks as $t) {
            $events[] = [
                'id' => 'task-' . $t->getId(),
                'sourceId' => $t->getId(),
                'title' => $t->getTitle(),
                'date' => ($t->getStartTime() ?? $t->getDate())->format('Y-m-d H:i:s'),
                'startDate' => ($t->getStartTime() ?? $t->getDate())->format('Y-m-d H:i:s'),
                'type' => 'TASK',
                'isCompleted' => $t->isCompleted(),
                'color' => 'bg-blue-50 text-blue-700 border-blue-100',
                'description' => $t->getDescription(),
                'linkedGoal' => $t->getGrowthGoal() ? $t->getGrowthGoal()->getTitle() : null,
                'linkedGoalId' => $t->getGrowthGoal() ? $t->getGrowthGoal()->getId() : null,
                'startTime' => $t->getStartTime() ? $t->getStartTime()->format('H:i') : null,
                'endTime' => $t->getEndTime() ? $t->getEndTime()->format('H:i') : null,
                'isAllDay' => $t->isAllDay(),
            ];
        }

        // Map Milestones
        /** @var LifeMilestone $m */
        foreach ($milestones as $m) {
            $events[] = [
                'id' => 'milestone-' . $m->getId(),
                'sourceId' => $m->getId(),
                'title' => 'Jalon: ' . $m->getTitle(),
                'date' => $m->getDueDate()->format('Y-m-d H:i:s'),
                'type' => 'MILESTONE',
                'isCompleted' => $m->isCompleted(),
                'color' => 'bg-purple-50 text-purple-700 border-purple-100',
                'linkedGoal' => $m->getLifeGoal()->getTitle(),
                'linkedGoalId' => $m->getLifeGoal()->getId(),
                'isAllDay' => true,
            ];
        }

        // Habits Projection
        $daysDiff = (int)$startDate->diff($endDate)->format('%a');
        for ($i = 0; $i <= $daysDiff; $i++) {
            $currentDate = (clone $startDate)->modify("+$i days");
            $currentDateStr = $currentDate->format('Y-m-d');
            $currentDayOfWeek = (int)$currentDate->format('w'); // 0 (Sun) - 6 (Sat)

            /** @var Habit $habit */
            foreach ($habits as $habit) {
                if ($habit->getEndDate() && $currentDate > $habit->getEndDate()) {
                    continue;
                }

                $shouldShow = false;
                $freq = $habit->getFrequency()->value;

                if ($freq === 'DAILY') {
                    $shouldShow = true;
                } elseif ($freq === 'WEEKLY') {
                    $targetDay = $habit->getRecurrenceDay() ?? 1; // Default Monday
                    if ($currentDayOfWeek === $targetDay) {
                        $shouldShow = true;
                    }
                } elseif ($freq === 'MONTHLY') {
                    if ($habit->getRecurrenceWeek()) {
                        $targetWeekday = $habit->getRecurrenceDay() ?? 0; // Default Sunday
                        if ($currentDayOfWeek === $targetWeekday) {
                            $weekNum = (int)ceil((int)$currentDate->format('j') / 7);
                            $lastDayOfMonth = (int)(clone $currentDate)->modify('last day of this month')->format('j');
                            $isLastOccurrence = ((int)$currentDate->format('j') + 7) > $lastDayOfMonth;

                            if ($habit->getRecurrenceWeek() === 5) {
                                if ($isLastOccurrence) {
                                    $shouldShow = true;
                                }
                            } else {
                                if ($weekNum === $habit->getRecurrenceWeek()) {
                                    $shouldShow = true;
                                }
                            }
                        }
                    } else {
                        $targetDate = $habit->getRecurrenceDay() ?? 1;
                        $lastDayOfMonth = (int)(clone $currentDate)->modify('last day of this month')->format('j');

                        if ((int)$currentDate->format('j') === $targetDate) {
                            $shouldShow = true;
                        } elseif ($targetDate > $lastDayOfMonth && (int)$currentDate->format('j') === $lastDayOfMonth) {
                            $shouldShow = true;
                        }
                    }
                }

                if ($shouldShow) {
                    $isCompleted = isset($logsMap[$habit->getId()][$currentDateStr]) && $logsMap[$habit->getId()][$currentDateStr];
                    $events[] = [
                        'id' => 'habit-' . $habit->getId() . '-' . $currentDate->getTimestamp(),
                        'sourceId' => $habit->getId(),
                        'title' => $habit->getTitle(),
                        'date' => $currentDate->format('Y-m-d H:i:s'),
                        'type' => 'HABIT',
                        'isCompleted' => $isCompleted,
                        'color' => 'bg-green-50 text-green-700 border-green-100',
                        'isAllDay' => true,
                        'linkedGoal' => $habit->getGrowthGoal() ? $habit->getGrowthGoal()->getTitle() : null,
                        'linkedGoalId' => $habit->getGrowthGoal() ? $habit->getGrowthGoal()->getId() : null,
                    ];
                }
            }
        }

        // Challenges Projection
        /** @var ChallengeParticipant $part */
        foreach ($participations as $part) {
            $challenge = $part->getChallenge();
            $chStart = $challenge->getStartDate();
            $chEnd = $challenge->getEndDate();

            for ($i = 0; $i <= $daysDiff; $i++) {
                $currentDate = (clone $startDate)->modify("+$i days");
                $currentDate->setTime(0, 0, 0);
                $currentDateStr = $currentDate->format('Y-m-d');
                $currentDayOfWeek = (int)$currentDate->format('w');

                if ($currentDate < $chStart) continue;
                if ($chEnd && $currentDate > $chEnd) continue;

                $shouldShow = false;
                $freq = $challenge->getFrequency();

                if ($freq === 'DAILY') {
                    if ($challenge->getRecurrenceDays()) {
                        try {
                            $days = json_decode($challenge->getRecurrenceDays(), true);
                            if (is_array($days) && in_array($currentDayOfWeek, $days)) {
                                $shouldShow = true;
                            }
                        } catch (\Exception $e) {
                            $shouldShow = true;
                        }
                    } else {
                        $shouldShow = true;
                    }
                } elseif ($freq === 'WEEKLY') {
                    $targetDay = $challenge->getRecurrenceDay() ?? 1;
                    if ($currentDayOfWeek === $targetDay) {
                        $shouldShow = true;
                    }
                } elseif ($freq === 'MONTHLY') {
                    if ($challenge->getRecurrenceOrdinal() && $challenge->getRecurrenceDay() !== null) {
                        $targetWeekday = $challenge->getRecurrenceDay();
                        $targetOrdinal = $challenge->getRecurrenceOrdinal();

                        if ($currentDayOfWeek === $targetWeekday) {
                            $weekNum = (int)ceil((int)$currentDate->format('j') / 7);
                            $lastDayOfMonth = (int)(clone $currentDate)->modify('last day of this month')->format('j');
                            $isLastOccurrence = ((int)$currentDate->format('j') + 7) > $lastDayOfMonth;

                            if ($targetOrdinal === 5) {
                                if ($isLastOccurrence) $shouldShow = true;
                            } else {
                                if ($weekNum === $targetOrdinal) $shouldShow = true;
                            }
                        }
                    } else {
                        $targetDate = $challenge->getRecurrenceDay() ?? 1;
                        if ((int)$currentDate->format('j') === $targetDate) {
                            $shouldShow = true;
                        }
                    }
                }

                if ($shouldShow) {
                    $isCompleted = isset($challengeLogsMap[$part->getId()][$currentDateStr]) && $challengeLogsMap[$part->getId()][$currentDateStr];
                    $events[] = [
                        'id' => 'challenge-' . $part->getId() . '-' . $currentDate->getTimestamp(),
                        'sourceId' => $part->getId(),
                        'title' => 'Défi: ' . $challenge->getTitle(),
                        'date' => $currentDate->format('Y-m-d H:i:s'),
                        'type' => 'CHALLENGE',
                        'isCompleted' => $isCompleted,
                        'color' => 'bg-orange-50 text-orange-700 border-orange-100',
                        'isAllDay' => true,
                        'description' => $challenge->getDescription()
                    ];
                }
            }
        }

        return new JsonResponse($events);
    }

    #[Route('/api/planner/task/create', name: 'api_planner_task_create', methods: ['POST'])]
    public function createTask(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data || empty($data['title']) || empty($data['date'])) {
            return new JsonResponse(['error' => 'Missing fields'], Response::HTTP_BAD_REQUEST);
        }

        $task = new PlannerTask();
        $task->setUser($user);
        $task->setTitle($data['title']);
        $task->setDescription($data['description'] ?? null);
        $task->setDate(new \DateTime($data['date']));

        if (!empty($data['startTime'])) {
            $task->setStartTime(new \DateTime($data['date'] . ' ' . $data['startTime']));
            $task->setIsAllDay(false);
        } else {
            $task->setIsAllDay(true);
        }

        if (!empty($data['endTime'])) {
            $task->setEndTime(new \DateTime($data['date'] . ' ' . $data['endTime']));
        }

        if (!empty($data['growthGoalId'])) {
            $growthGoal = $em->getRepository(GrowthGoal::class)->find($data['growthGoalId']);
            if ($growthGoal && $growthGoal->getPlanDeCroissance()->getUser() === $user) {
                $task->setGrowthGoal($growthGoal);
            }
        }

        $em->persist($task);
        $em->flush();

        return new JsonResponse(['success' => true, 'id' => $task->getId()]);
    }

    #[Route('/api/planner/task/{id}/update', name: 'api_planner_task_update', methods: ['POST', 'PUT'])]
    public function updateTask(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $task = $em->getRepository(PlannerTask::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$task) {
            return new JsonResponse(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        if (isset($data['title'])) {
            $task->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $task->setDescription($data['description']);
        }
        if (isset($data['date'])) {
            $task->setDate(new \DateTime($data['date']));
        }
        if (isset($data['startTime'])) {
            if ($data['startTime']) {
                $baseDate = $data['date'] ?? $task->getDate()->format('Y-m-d');
                $task->setStartTime(new \DateTime($baseDate . ' ' . $data['startTime']));
                $task->setIsAllDay(false);
            } else {
                $task->setStartTime(null);
                $task->setIsAllDay(true);
            }
        }
        if (isset($data['endTime'])) {
            if ($data['endTime']) {
                $baseDate = $data['date'] ?? $task->getDate()->format('Y-m-d');
                $task->setEndTime(new \DateTime($baseDate . ' ' . $data['endTime']));
            } else {
                $task->setEndTime(null);
            }
        }
        if (isset($data['isCompleted'])) {
            $task->setIsCompleted($data['isCompleted']);
        }
        if (array_key_exists('growthGoalId', $data)) {
            if ($data['growthGoalId']) {
                $growthGoal = $em->getRepository(GrowthGoal::class)->find($data['growthGoalId']);
                if ($growthGoal && $growthGoal->getPlanDeCroissance()->getUser() === $user) {
                    $task->setGrowthGoal($growthGoal);
                }
            } else {
                $task->setGrowthGoal(null);
            }
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/planner/task/{id}/delete', name: 'api_planner_task_delete', methods: ['DELETE'])]
    public function deleteTask(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $task = $em->getRepository(PlannerTask::class)->findOneBy(['id' => $id, 'user' => $user]);

        if (!$task) {
            return new JsonResponse(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($task);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/planner/toggle/{type}/{id}', name: 'api_planner_toggle', methods: ['POST'])]
    public function toggleItem(string $type, string $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $dateStr = $data['date'] ?? 'today';
        $targetDate = new \DateTime($dateStr);
        $targetDate->setTime(0, 0, 0);

        if ($type === 'TASK') {
            $task = $em->getRepository(PlannerTask::class)->findOneBy(['id' => (int)$id, 'user' => $user]);
            if ($task) {
                $task->setIsCompleted(!$task->isCompleted());
                $em->flush();
                return new JsonResponse(['success' => true, 'isCompleted' => $task->isCompleted()]);
            }
        } elseif ($type === 'HABIT') {
            $habit = $em->getRepository(Habit::class)->findOneBy(['id' => (int)$id, 'user' => $user]);
            if ($habit) {
                // Find log for this specific date
                $log = $em->getRepository(HabitLog::class)->createQueryBuilder('hl')
                    ->where('hl.habit = :habit')
                    ->andWhere('hl.date = :date')
                    ->setParameter('habit', $habit)
                    ->setParameter('date', $targetDate)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($log) {
                    $em->remove($log);
                    $isCompleted = false;
                } else {
                    $log = new HabitLog();
                    $log->setHabit($habit);
                    $log->setDate($targetDate);
                    $log->setStatus(true);
                    $em->persist($log);
                    $isCompleted = true;
                }
                $em->flush();
                return new JsonResponse(['success' => true, 'isCompleted' => $isCompleted]);
            }
        } elseif ($type === 'MILESTONE') {
            $milestone = $em->getRepository(LifeMilestone::class)->find((int)$id);
            if ($milestone && $milestone->getLifeGoal()->getPlanDeVie()->getUser() === $user) {
                $milestone->setCompleted(!$milestone->isCompleted());
                $em->flush();
                return new JsonResponse(['success' => true, 'isCompleted' => $milestone->isCompleted()]);
            }
        } elseif ($type === 'CHALLENGE') {
            $participation = $em->getRepository(ChallengeParticipant::class)->findOneBy(['id' => (int)$id, 'user' => $user]);
            if ($participation) {
                $log = $em->getRepository(ChallengeLog::class)->createQueryBuilder('cl')
                    ->where('cl.participant = :part')
                    ->andWhere('cl.date = :date')
                    ->setParameter('part', $participation)
                    ->setParameter('date', $targetDate)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($log) {
                    $em->remove($log);
                    $isCompleted = false;
                } else {
                    $log = new ChallengeLog();
                    $log->setParticipant($participation);
                    $log->setDate($targetDate);
                    $log->setStatus(true);
                    $em->persist($log);
                    $isCompleted = true;

                    // Add activity feed event
                    $activity = new ChallengeActivity();
                    $activity->setChallenge($participation->getChallenge());
                    $activity->setUser($user);
                    $activity->setType('SYSTEM');
                    $activity->setContent(($user->getUserProfile() ? $user->getUserProfile()->getFirstName() : $user->getEmail()) . ' a validé son défi depuis le calendrier !');
                    $em->persist($activity);
                }

                $em->flush();

                // Re-sync progress
                $logCount = $em->getRepository(ChallengeLog::class)->createQueryBuilder('cl')
                    ->select('COUNT(cl.id)')
                    ->where('cl.participant = :part')
                    ->setParameter('part', $participation)
                    ->getQuery()
                    ->getSingleScalarResult();

                $participation->setProgress((int)$logCount);
                $em->flush();

                return new JsonResponse(['success' => true, 'isCompleted' => $isCompleted]);
            }
        }

        return new JsonResponse(['error' => 'Item not found'], Response::HTTP_NOT_FOUND);
    }

    #[Route('/api/planner/intention', name: 'api_planner_intention', methods: ['POST'])]
    public function updateIntention(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $intention = $data['intention'] ?? '';
        $dateStr = $data['date'] ?? 'today';
        $targetDate = new \DateTime($dateStr);
        $targetDate->setTime(0, 0, 0);

        $planDHygiene = $user->getPlanDHygiene();
        if (!$planDHygiene) {
            return new JsonResponse(['error' => 'Plan D\'Hygiene not found'], Response::HTTP_BAD_REQUEST);
        }

        $log = $em->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $planDHygiene, 'date' => $targetDate]);

        if ($log) {
            $log->setIntention($intention);
        } else {
            $log = new DailyLog();
            $log->setPlanDHygiene($planDHygiene);
            $log->setDate($targetDate);
            $log->setIntention($intention);
            $em->persist($log);
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'intention' => $intention]);
    }

    #[Route('/api/planner/daily-intention', name: 'api_planner_get_daily_intention', methods: ['GET'])]
    public function getDailyIntention(Request $request, EntityManagerInterface $em): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $dateStr = $request->query->get('date', 'today');
        $targetDate = new \DateTime($dateStr);
        $targetDate->setTime(0, 0, 0);

        $planDHygiene = $user->getPlanDHygiene();
        if (!$planDHygiene) {
            return new JsonResponse(['intention' => '']);
        }

        $log = $em->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $planDHygiene, 'date' => $targetDate]);

        return new JsonResponse(['intention' => $log ? $log->getIntention() : '']);
    }
}
