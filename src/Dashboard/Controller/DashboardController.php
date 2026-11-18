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
    /** Page d'accueil publique (présentation et prix) ; un utilisateur connecté va à son tableau de bord. */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(\App\Abonnement\Service\SubscriptionManager $prices, \App\Abonnement\Service\Entitlements $entitlements, \App\Abonnement\Service\CurrencyResolver $currencies, \Symfony\Component\HttpFoundation\Request $request, \App\Media\Service\FunnelReport $funnel): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        $funnel->countVisit($request);

        return $this->render('dashboard/landing.html.twig', [
            'defaultCurrency' => $currencies->resolve($request),
            'cfaTimezones' => \App\Abonnement\Service\CurrencyResolver::CFA_TIMEZONES,
            'prices' => [
                'EUR' => ['MONTHLY' => $prices->price('MONTHLY', 'EUR'), 'YEARLY' => $prices->price('YEARLY', 'EUR'), 'DUO' => $prices->price('DUO', 'EUR')],
                'XAF' => ['MONTHLY' => $prices->price('MONTHLY', 'XAF'), 'YEARLY' => $prices->price('YEARLY', 'XAF'), 'DUO' => $prices->price('DUO', 'XAF')],
            ],
            'trialDays' => $entitlements->config()->getTrialDurationDays(),
            'freeHabits' => $entitlements->config()->getMaxHabitsFree(),
            // Prix d'une place au plus grand pack (remise maximale)
            'orgSeatEur' => round($entitlements->config()->getOrgSeatYearlyPriceEur() * (1 - max(\App\Organisation\Service\OrganisationService::PACKS)), 2),
            'orgSeatXaf' => round($entitlements->config()->getOrgSeatYearlyPriceXaf() * (1 - max(\App\Organisation\Service\OrganisationService::PACKS)), -2),
        ]);
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(EntityManagerInterface $entityManager, DashboardData $dashboard, \App\Defis\Service\ChallengeService $challenges, \App\Dashboard\Service\NextStep $nextStep): Response
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

        // Parcours de 90 jours (livre, annexe A) : rappel de la semaine en cours
        $journey = $entityManager->getRepository(\App\Methode\Entity\JourneyProgress::class)->findOneBy(['user' => $user]);

        return $this->render('dashboard/index.html.twig', [
            'nextStep' => $nextStep->forUser($user),
            'journey' => $journey,
            'journeyWeek' => $journey && !$journey->isFinished() ? \App\Methode\Service\Journey::PLAN[$journey->currentWeek()] + ['n' => $journey->currentWeek()] : null,
            'user' => $user,
            'quote' => $dashboard->quote(),
            'stats' => $dashboard->stats($habits),
            'deadlines' => $dashboard->upcomingDeadlines($user),
            'habits' => $habits,
            'nextSteps' => $dashboard->nextSteps($user, $habits),
            'challenges' => array_map(fn ($p) => [
                'p' => $p, 'c' => $p->getChallenge(), 'phase' => $challenges->phase($p->getChallenge(), new \DateTimeImmutable('today')),
                'day' => $challenges->dayNumber($p->getChallenge(), new \DateTimeImmutable('today')),
                'checked' => $challenges->hasCheckedIn($p, new \DateTimeImmutable('today')),
            ], $challenges->activeParticipations($user, new \DateTimeImmutable('today'))),
            'growthGoal' => $user->getPlanDeCroissance(),
            'planDeVie' => $user->getPlanDeVie(),
        ]);
    }
}
