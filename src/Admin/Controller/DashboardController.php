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
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    private const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

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
        Connection $db,
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
            'labels' => ['Plan de Vie', 'Plan de Croissance', 'Plan de Développement personnel', 'Plan d\'Hygiène de vie', 'Plan de Transmission'],
            'data' => array_values($plansData),
            // Graphique (JavaScript) : les variables CSS n'y fonctionnent pas ; couleurs des plans de docs/design.md
            'colors' => ['#C8962E', '#1F4D3A', '#2E6B53', '#5D7368', '#A87A1F'],
        ];

        // --- Chart data: Activity (habit logs per month, last 6 months) ---
        $activityLabels = [];
        $activityData = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = new \DateTime("first day of -$i months midnight");
            $end = (clone $start)->modify('last day of this month 23:59:59');
            $activityLabels[] = self::MONTHS[(int) $start->format('n') - 1].' '.$start->format('Y');

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

        // --- Membres : arrivées, activité, abonnements (mêmes définitions que Paiements et Rétention) ---
        $count = static function (string $sql) use ($db): int {
            return (int) $db->fetchOne($sql);
        };
        $members = [
            'new7' => $count('SELECT COUNT(*) FROM `user` WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)'),
            'active30' => $count('SELECT COUNT(*) FROM `user` WHERE last_active_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'pro' => $count("SELECT COUNT(*) FROM subscription WHERE tier <> 'FREE' AND status = 'ACTIVE' AND current_period_end > NOW()"),
            'trial' => $count("SELECT COUNT(*) FROM subscription WHERE status = 'TRIAL' AND trial_end_at > NOW()"),
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
            'members' => $members,
        ]);
    }
}
