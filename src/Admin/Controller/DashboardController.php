<?php

namespace App\Admin\Controller;

use App\Defis\Repository\ChallengeRepository;
use App\PlanDeCroissance\Repository\GrowthGoalRepository;
use App\Rituels\Repository\HabitLogRepository;
use App\Rituels\Repository\HabitRepository;
use App\Journal\Repository\JournalCollectionRepository;
use App\PlanDeCroissance\Repository\PlanDeCroissanceRepository;
use App\PlanDeDeveloppement\Repository\PlanDeDeveloppementPersonnelRepository;
use App\PlanDeTransmission\Repository\PlanDeTransmissionRepository;
use App\PlanDeVie\Repository\PlanDeVieRepository;
use App\PlanDHygiene\Repository\PlanDHygieneRepository;
use App\Personnalite\Repository\ProfileTestRepository;
use App\Journal\Repository\ReflectionNoteRepository;
use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    #[Route('', name: 'admin_dashboard', methods: ['GET'])]
    public function index(
        UserRepository $userRepo,
        PlanDeVieRepository $planVieRepo,
        PlanDeCroissanceRepository $planCroissanceRepo,
        PlanDeDeveloppementPersonnelRepository $planPDPRepo,
        PlanDHygieneRepository $planHygieneRepo,
        PlanDeTransmissionRepository $planTransmissionRepo,
        HabitRepository $habitRepo,
        HabitLogRepository $habitLogRepo,
        GrowthGoalRepository $growthGoalRepo,
        JournalCollectionRepository $journalRepo,
        ReflectionNoteRepository $noteRepo,
        ProfileTestRepository $testRepo,
        ChallengeRepository $challengeRepo,
    ): Response {
        // --- Core metrics ---
        $totalUsers = $userRepo->count();
        $totalAdmins = (int) $userRepo->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getSingleScalarResult();

        // --- Plans metrics ---
        $plansData = [
            'vie' => $planVieRepo->count(),
            'croissance' => $planCroissanceRepo->count(),
            'pdp' => $planPDPRepo->count(),
            'hygiene' => $planHygieneRepo->count(),
            'transmission' => $planTransmissionRepo->count(),
        ];
        $totalPlans = array_sum($plansData);

        // --- Habits metrics ---
        $totalHabits = $habitRepo->count();
        $totalHabitLogs = $habitLogRepo->count();

        // --- Growth goals ---
        $totalGrowthGoals = $growthGoalRepo->count();

        // --- Journaling ---
        $totalJournalCollections = $journalRepo->count();
        $totalReflectionNotes = $noteRepo->count();

        // --- Tests & Challenges ---
        $totalTests = $testRepo->count();
        $totalChallenges = $challengeRepo->count();

        // --- Chart data: Plans distribution ---
        $plansChartData = [
            'labels' => ['Vie', 'Croissance', 'PDP', 'Hygiène', 'Transmission'],
            'data' => array_values($plansData),
            'colors' => ['#6366f1', '#8b5cf6', '#a855f7', '#c084fc', '#e879f9'],
        ];

        // --- Chart data: Activity (habit logs per month, last 6 months) ---
        $activityLabels = [];
        $activityData = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = new \DateTime("first day of -$i months midnight");
            $end = (clone $start)->modify('last day of this month 23:59:59');
            $activityLabels[] = $start->format('M Y');

            $count = (int) $habitLogRepo->createQueryBuilder('hl')
                ->select('COUNT(hl.id)')
                ->where('hl.date >= :start')
                ->andWhere('hl.date <= :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end)
                ->getQuery()
                ->getSingleScalarResult();

            $activityData[] = $count;
        }

        $activityChartData = [
            'labels' => $activityLabels,
            'data' => $activityData,
        ];

        // --- Recent users ---
        $recentUsers = $userRepo->findBy([], ['id' => 'DESC'], 5);

        return $this->render('admin/dashboard.html.twig', [
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalPlans' => $totalPlans,
            'plansData' => $plansData,
            'totalHabits' => $totalHabits,
            'totalHabitLogs' => $totalHabitLogs,
            'totalGrowthGoals' => $totalGrowthGoals,
            'totalJournalCollections' => $totalJournalCollections,
            'totalReflectionNotes' => $totalReflectionNotes,
            'totalTests' => $totalTests,
            'totalChallenges' => $totalChallenges,
            'plansChartData' => $plansChartData,
            'activityChartData' => $activityChartData,
            'recentUsers' => $recentUsers,
        ]);
    }
}
