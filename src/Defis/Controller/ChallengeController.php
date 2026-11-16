<?php

namespace App\Defis\Controller;

use App\Compte\Entity\User;
use App\Defis\Entity\Challenge;
use App\Defis\Service\ChallengeService;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Organisation\Service\PendingInvitation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Défis collectifs : pages publiques (partagées sur les réseaux), inscription en un clic — y compris sans compte,
 * via la création de compte —, validation quotidienne, progression et classement.
 */
class ChallengeController extends AbstractController
{
    public function __construct(private readonly ChallengeService $challenges, private readonly Notifier $notifier)
    {
    }

    #[Route('/defis', name: 'app_challenges', methods: ['GET'])]
    public function index(): Response
    {
        $today = new \DateTimeImmutable('today');
        $mine = $this->getUser() instanceof User ? $this->challenges->activeParticipations($this->getUser(), $today) : [];

        return $this->render('defis/index.html.twig', [
            'open' => $this->challenges->openChallenges($today),
            'mine' => array_map(fn ($p) => $p->getChallenge()->getId(), $mine),
            'service' => $this->challenges,
            'today' => $today,
        ]);
    }

    #[Route('/defi/{slug}', name: 'app_challenge', requirements: ['slug' => '[a-z0-9-]{3,80}'], methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        $challenge = $this->challenges->findPublished($slug) ?? throw $this->createNotFoundException('Ce défi n\'existe pas ou n\'est plus ouvert.');
        $today = new \DateTimeImmutable('today');
        $user = $this->getUser();
        $participation = $user instanceof User ? $this->challenges->participation($challenge, $user) : null;

        // Retour après création de compte ou connexion : inscription automatique au défi choisi
        $session = $request->getSession();
        if ($user instanceof User && $slug === $session->get(PendingInvitation::CHALLENGE_KEY)) {
            $session->remove(PendingInvitation::CHALLENGE_KEY);
            if (null === $participation && 'ended' !== $this->challenges->phase($challenge, $today)) {
                $participation = $this->joinAndWelcome($challenge, $user);
                $this->addFlash('success', 'Vous relevez le défi « '.$challenge->getTitle().' ». Bravo !');
            }
        }

        $day = $this->challenges->dayNumber($challenge, $today);

        return $this->render('defis/show.html.twig', [
            'challenge' => $challenge,
            'phase' => $this->challenges->phase($challenge, $today),
            'day' => $day,
            'today' => $today,
            'participation' => $participation,
            'checkedToday' => $participation && $this->challenges->hasCheckedIn($participation, $today),
            'streak' => $participation ? $this->challenges->streak($participation, $today) : 0,
            'successful' => $participation && $this->challenges->isSuccessful($participation),
            'threshold' => $this->challenges->successThreshold($challenge),
            'stats' => $this->challenges->stats($challenge, $today),
            'leaderboard' => $this->challenges->leaderboard($challenge, $user instanceof User ? $user : null),
            'shareUrl' => $this->generateUrl('app_challenge', ['slug' => $slug], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/defi/{slug}/rejoindre', name: 'app_challenge_join', requirements: ['slug' => '[a-z0-9-]{3,80}'], methods: ['POST'])]
    public function join(string $slug, Request $request): Response
    {
        $challenge = $this->challenges->findPublished($slug) ?? throw $this->createNotFoundException();
        if (!$this->isCsrfTokenValid('challenge_join', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('app_challenge', ['slug' => $slug]);
        }
        $user = $this->getUser();
        if (!$user instanceof User) {
            $request->getSession()->set(PendingInvitation::CHALLENGE_KEY, $slug);

            return $this->redirectToRoute($request->request->getBoolean('hasAccount') ? 'app_login' : 'app_register');
        }
        if ('ended' === $this->challenges->phase($challenge, new \DateTimeImmutable('today'))) {
            $this->addFlash('error', 'Ce défi est terminé.');
        } elseif (null === $this->challenges->participation($challenge, $user)) {
            $this->joinAndWelcome($challenge, $user);
            $this->addFlash('success', 'Vous relevez le défi « '.$challenge->getTitle().' ». Bravo !');
        }

        return $this->redirectToRoute('app_challenge', ['slug' => $slug]);
    }

    #[Route('/defi/{slug}/valider', name: 'app_challenge_checkin', requirements: ['slug' => '[a-z0-9-]{3,80}'], methods: ['POST'])]
    public function checkIn(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $challenge = $this->challenges->findPublished($slug) ?? throw $this->createNotFoundException();
        $participation = $this->challenges->participation($challenge, $this->getUser());
        if (null === $participation || !$this->isCsrfTokenValid('challenge_checkin', $request->request->getString('_csrf_token'))) {
            return $this->redirectToRoute('app_challenge', ['slug' => $slug]);
        }
        $today = new \DateTimeImmutable('today');
        $wasSuccessful = $this->challenges->isSuccessful($participation);
        if ($this->challenges->checkIn($participation, $today)) {
            $this->addFlash('success', !$wasSuccessful && $this->challenges->isSuccessful($participation)
                ? '🏆 Défi relevé ! Vous avez atteint le seuil de réussite. Continuez jusqu\'au bout.'
                : 'Jour '.$this->challenges->dayNumber($challenge, $today).' validé. Fidèle dans les petites choses !');
        }

        return 'dashboard' === $request->request->getString('from')
            ? $this->redirectToRoute('app_dashboard')
            : $this->redirectToRoute('app_challenge', ['slug' => $slug]);
    }

    private function joinAndWelcome(Challenge $challenge, User $user): \App\Defis\Entity\ChallengeParticipant
    {
        $participation = $this->challenges->join($challenge, $user);
        $today = new \DateTimeImmutable('today');
        $this->notifier->notify($user, NotificationType::CHALLENGE_DAY, 'Vous relevez le défi « '.$challenge->getTitle().' »', $challenge->getTitle(),
            '/defi/'.$challenge->getSlug(), 'defi '.$challenge->getId().' inscription', ['template' => 'defis/email/welcome.html.twig', 'context' => [
                'challenge' => $challenge, 'phase' => $this->challenges->phase($challenge, $today),
                'day' => $this->challenges->dayNumber($challenge, $today), 'threshold' => $this->challenges->successThreshold($challenge),
            ]]);

        return $participation;
    }
}
