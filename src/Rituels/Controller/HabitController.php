<?php

namespace App\Rituels\Controller;

use App\Abonnement\Service\Entitlements;

use App\Rituels\Entity\Habit;
use App\Rituels\Entity\HabitLog;
use App\Rituels\Form\HabitType;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class HabitController extends AbstractController
{
    #[Route('/mes-plans/rituels', name: 'app_user_habits')]
    public function rituels(Request $request, EntityManagerInterface $em, Entitlements $entitlements): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $habit = new Habit();
        $habit->setUser($user);

        $form = $this->createForm(HabitType::class, $habit);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entitlements->requireBelowLimit($user, 'habits', $user->getHabits()->count() - 1, 'Limite de rituels de l\'offre gratuite atteinte.');
            $em->persist($habit);
            $em->flush();

            return $this->redirectToRoute('app_user_habits');
        }

        // Get growth goals to let the user link their habits
        $growthGoals = [];
        if ($user->getPlanDeCroissance()) {
            foreach ($user->getPlanDeCroissance()->getGrowthGoals() as $gg) {
                $growthGoals[] = [
                    'id' => $gg->getId(),
                    'title' => $gg->getTitle(),
                    'axis' => $gg->getAxis()->value,
                ];
            }
        }

        $habits = $em->getRepository(Habit::class)->findByWithAllRelations($user);

        return $this->render('rituels/index.html.twig', [
            'habits' => $habits,
            'growthGoals' => $growthGoals,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/api/habits/{id}/log', name: 'api_habit_log', methods: ['POST'])]
    public function toggleLog(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $habit = $em->getRepository(Habit::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$habit) {
            return new JsonResponse(['error' => 'Habit not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $status = filter_var($data['status'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $today = new \DateTime('today');
        
        // Find existing log for today
        $habitLog = null;
        foreach ($habit->getLogs() as $log) {
            if ($log->getDate()->format('Y-m-d') === $today->format('Y-m-d')) {
                $habitLog = $log;
                break;
            }
        }

        if ($habitLog) {
            $habitLog->setStatus($status);
        } else {
            $habitLog = new HabitLog();
            $habitLog->setDate($today);
            $habitLog->setStatus($status);
            $habit->addLog($habitLog);
            $em->persist($habitLog);
        }

        $em->flush();

        return new JsonResponse(['success' => true, 'isCompleted' => $habit->isCompletedForCurrentPeriod()]);
    }

    #[Route('/api/habits/{id}/rooting', name: 'api_habit_rooting', methods: ['POST'])]
    public function saveRooting(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Habit $habit */
        $habit = $em->getRepository(Habit::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$habit) {
            return new JsonResponse(['error' => 'Habit not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        // Update rooting details
        $habit->setImplementationWhen($data['implementationWhen'] ?? null);
        $habit->setImplementationThen($data['implementationThen'] ?? null);
        $habit->setSupportStrategy($data['supportStrategy'] ?? null);
        $habit->setRootingIntention($data['rootingIntention'] ?? null);
        $habit->setRootingAction($data['rootingAction'] ?? null);
        $habit->setRootingMoment($data['rootingMoment'] ?? null);
        $habit->setRootingAnchor($data['rootingAnchor'] ?? null);
        $habit->setRootingBenefit($data['rootingBenefit'] ?? null);
        $habit->setRootingFacilitator($data['rootingFacilitator'] ?? null);

        if (isset($data['frequency'])) {
            $freq = HabitFrequency::tryFrom($data['frequency']);
            if ($freq) {
                $habit->setFrequency($freq);
            }
        }
        if (isset($data['recurrenceDay'])) {
            $habit->setRecurrenceDay($data['recurrenceDay'] !== null ? (int)$data['recurrenceDay'] : null);
        }
        if (isset($data['recurrenceWeek'])) {
            $habit->setRecurrenceWeek($data['recurrenceWeek'] !== null ? (int)$data['recurrenceWeek'] : null);
        }
        if (isset($data['endDate']) && !empty($data['endDate'])) {
            $habit->setEndDate(new \DateTime($data['endDate']));
        } elseif (array_key_exists('endDate', $data)) {
            $habit->setEndDate(null);
        }

        if (isset($data['growthGoalId']) && !empty($data['growthGoalId'])) {
            $gg = $em->getRepository(\App\PlanDeCroissance\Entity\GrowthGoal::class)->findOneBy([
                'id' => (int)$data['growthGoalId'],
                'planDeCroissance' => $user->getPlanDeCroissance()
            ]);
            if ($gg) {
                $habit->setGrowthGoal($gg);
            }
        }

        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/habits/{id}', name: 'api_habit_delete', methods: ['DELETE'])]
    public function deleteHabit(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $habit = $em->getRepository(Habit::class)->findOneBy(['id' => $id, 'user' => $user]);
        if (!$habit) {
            return new JsonResponse(['error' => 'Habit not found'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($habit);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
}
