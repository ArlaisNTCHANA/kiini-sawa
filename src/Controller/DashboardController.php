<?php

namespace App\Controller;

use App\Entity\Habit;
use App\Entity\User;
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
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        if (!$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding');
        }

        // Dummy quote
        $quote = [
            'content' => 'Le succès n\'est pas final, l\'échec n\'est pas fatal : c\'est le courage de continuer qui compte.',
            'author' => 'Winston Churchill'
        ];

        $habits = $entityManager->getRepository(Habit::class)->findByWithAllRelations($user);

        return $this->render('dashboard/index.html.twig', [
            'user' => $user,
            'quote' => $quote,
            'habits' => $habits,
            'growthGoal' => $user->getPlanDeCroissance(),
            'planDeVie' => $user->getPlanDeVie(),
        ]);
    }
}
