<?php

namespace App\Notification\Controller;

use App\Compte\Entity\User;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Centre de notifications : liste, lecture, désinscription d'un rappel depuis un email.
 */
class NotificationController extends AbstractController
{
    /** Autorisation des notifications sur cet appareil (appelé par le navigateur, en JSON). */
    #[Route('/notifications/appareil', name: 'app_push_subscribe', methods: ['POST'])]
    public function pushSubscribe(Request $request, \App\Notification\Service\PushSender $push, \App\Shared\Security\RateLimits $limits): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $endpoint = (string) ($data['endpoint'] ?? '');
        $p256dh = (string) ($data['keys']['p256dh'] ?? '');
        $auth = (string) ($data['keys']['auth'] ?? '');
        $remove = 'remove' === ($data['action'] ?? null);
        // Retirer un appareil ne contacte jamais son adresse : seule l'inscription est soumise à la liste des services Web Push officiels
        if (!$user instanceof \App\Compte\Entity\User || '' === $endpoint || \strlen($endpoint) > 2000
            || (!$remove && ('' === $p256dh || '' === $auth || !$push->isEndpointAllowed($endpoint)))) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['ok' => false], 400);
        }
        if ($remove) {
            $push->unsubscribe($user, $endpoint);
        } else {
            try {
                $push->subscribe($user, $endpoint, mb_substr($p256dh, 0, 255), mb_substr($auth, 0, 255), $request->headers->get('User-Agent'));
            } catch (\InvalidArgumentException $e) {
                return new \Symfony\Component\HttpFoundation\JsonResponse(['ok' => false, 'error' => $e->getMessage()], 400);
            }
            // Notification d'essai : au plus 3 par heure et par compte (sinon, c'est un moyen d'envoyer des messages en boucle)
            if (true === ($data['test'] ?? false) && $limits->allow('push_test', (string) $user->getId())) {
                $push->send($user, 'Notifications activées ✓', 'Vous recevrez ici vos rappels de rituels, votre revue du dimanche et l\'action du jour de vos défis.', '/dashboard', 'bienvenue');
            }
        }

        return new \Symfony\Component\HttpFoundation\JsonResponse(['ok' => true]);
    }

    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationRepository $notifications): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('notification/index.html.twig', ['notifications' => $notifications->findRecent($user)]);
    }

    #[Route('/notifications/{id}/ouvrir', name: 'app_notification_open', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function open(Notification $notification, Request $request, EntityManagerInterface $em): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }
        if ($this->isCsrfTokenValid('notification', $request->request->getString('_csrf_token'))) {
            $notification->setRead(true);
            $em->flush();
        }
        $url = $notification->getActionUrl();

        // Seuls les chemins internes sont suivis (pas de redirection vers un autre site)
        return $url && str_starts_with($url, '/') && !str_starts_with($url, '//') ? $this->redirect($url) : $this->redirectToRoute('app_notifications');
    }

    #[Route('/notifications/tout-lu', name: 'app_notifications_read_all', methods: ['POST'])]
    public function readAll(Request $request, NotificationRepository $notifications): Response
    {
        if ($this->isCsrfTokenValid('notification', $request->request->getString('_csrf_token'))) {
            $notifications->markAllRead($this->getUser());
        }

        return $this->redirectToRoute('app_notifications');
    }

    /** Lien signé reçu par email : désactive ce type de rappel, sans avoir à se connecter. */
    #[Route('/notifications/desabonnement/{user}/{type}', name: 'app_notification_unsubscribe', requirements: ['user' => '\d+'], methods: ['GET', 'POST'])]
    public function unsubscribe(int $user, string $type, Request $request, UriSigner $signer, EntityManagerInterface $em, Notifier $notifier, \App\Academie\Service\AcademyPaceMailer $academy): Response
    {
        $account = $em->find(User::class, $user);
        $notificationType = NotificationType::tryFrom($type);
        if (!$signer->checkRequest($request) || !$account || !$notificationType) {
            return $this->render('notification/unsubscribe.html.twig', ['done' => false, 'valid' => false], new Response(status: 400));
        }

        $done = false;
        if ($request->isMethod('POST')) {
            $prefs = $notifier->preferences($account);
            match ($notificationType) {
                NotificationType::WEEKLY_REVIEW_REMINDER => $prefs->setNotifyWeeklyReview(false),
                NotificationType::MONTHLY_REVIEW_REMINDER => $prefs->setNotifyMonthlyReview(false),
                NotificationType::HABIT_REMINDER => $prefs->setNotifyHabitReminder(false),
                NotificationType::GOAL_DEADLINE => $prefs->setNotifyGoalDeadline(false),
                NotificationType::PARTNER_INACTIVE => $prefs->setNotifyPartnerInactive(false),
                NotificationType::ONBOARDING => $prefs->setNotifyOnboarding(false),
                NotificationType::CHALLENGE_DAY => $prefs->setNotifyChallenge(false),
                NotificationType::BIRTHDAY => $prefs->setNotifyBirthday(false),
                // Académie : les rythmes par e-mail du membre passent en pause (reprise en un clic sur la page du parcours)
                NotificationType::ACADEMY_LESSON => $academy->pauseAll($account),
                default => $prefs->setEnableEmailInstant(false),
            };
            $em->flush();
            $done = true;
        }

        return $this->render('notification/unsubscribe.html.twig', ['done' => $done, 'valid' => true]);
    }
}
