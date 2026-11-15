<?php

namespace App\Dashboard\Controller;

use App\Rituels\Entity\Habit;
use App\Compte\Entity\User;
use App\Dashboard\Service\DashboardData;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(EntityManagerInterface $entityManager, DashboardData $dashboard): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding');
        }

        $habits = $entityManager->getRepository(Habit::class)->findByWithAllRelations($user);

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'quote' => $dashboard->quote(),
            'stats' => $dashboard->stats($habits),
            'deadlines' => $dashboard->upcomingDeadlines($user),
            'habits' => $habits,
            'growthGoal' => $user->getPlanDeCroissance(),
            'planDeVie' => $user->getPlanDeVie(),
        ]);
    }
}
