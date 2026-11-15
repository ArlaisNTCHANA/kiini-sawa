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
    public function unsubscribe(int $user, string $type, Request $request, UriSigner $signer, EntityManagerInterface $em, Notifier $notifier): Response
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
                default => $prefs->setEnableEmailInstant(false),
            };
            $em->flush();
            $done = true;
        }

        return $this->render('notification/unsubscribe.html.twig', ['done' => $done, 'valid' => true]);
    }
}
