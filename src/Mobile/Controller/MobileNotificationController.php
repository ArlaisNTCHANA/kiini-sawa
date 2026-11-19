<?php

namespace App\Mobile\Controller;

use App\Mobile\Service\ExpoPushSender;
use App\Notification\Entity\Notification;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\Notifier;
use App\Shared\Security\RateLimits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des notifications pour l'application mobile :
 * enregistrement du jeton Expo Push, préférences de rappels, notification d'essai,
 * et centre de notifications in-app.
 */
#[Route('/api/mobile/v1/notifications')]
class MobileNotificationController extends AbstractController
{
    use MobileJsonTrait;

    #[Route('/appareil', name: 'api_mobile_notifications_device_register', methods: ['POST'])]
    public function registerDevice(Request $request, ExpoPushSender $expo): JsonResponse
    {
        $user = $this->currentMobileUser();
        $data = $this->decodeJson($request);
        $token = (string) ($data['expoToken'] ?? '');

        if ('' === trim($token)) {
            return $this->jsonError('Jeton Expo Push requis.', Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        $platform = (string) ($data['platform'] ?? 'unknown');
        $deviceName = isset($data['appareil']) && \is_string($data['appareil']) ? $data['appareil'] : null;

        try {
            $device = $expo->register($user, $token, $platform, $deviceName);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), Response::HTTP_BAD_REQUEST, 'invalid_push_device');
        }

        return $this->jsonNoStore([
            'ok' => true,
            'hasDevice' => true,
            'platform' => $device->getPlatform(),
        ]);
    }

    #[Route('/appareil', name: 'api_mobile_notifications_device_unregister', methods: ['DELETE'])]
    public function unregisterDevice(Request $request, ExpoPushSender $expo): JsonResponse
    {
        $user = $this->currentMobileUser();
        $data = $this->decodeJson($request);
        $token = (string) ($data['expoToken'] ?? '');

        if ('' !== trim($token)) {
            $expo->unregister($user, $token);
        }

        return $this->jsonNoStore([
            'ok' => true,
            'hasDevice' => $expo->hasDevice($user),
        ]);
    }

    #[Route('/preferences', name: 'api_mobile_notifications_preferences_get', methods: ['GET'])]
    public function getPreferences(Notifier $notifier, ExpoPushSender $expo): JsonResponse
    {
        $user = $this->currentMobileUser();
        $prefs = $notifier->preferences($user);

        return $this->jsonNoStore([
            'preferences' => $this->serializePreferences($prefs),
            'hasPushDevice' => $expo->hasDevice($user),
        ]);
    }

    #[Route('/preferences', name: 'api_mobile_notifications_preferences_update', methods: ['PATCH'])]
    public function updatePreferences(
        Request $request,
        Notifier $notifier,
        ExpoPushSender $expo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->currentMobileUser();
        $prefs = $notifier->preferences($user);
        $data = $this->decodeJson($request);

        if (null === $data) {
            return $this->jsonError('Données de préférences requises.', Response::HTTP_BAD_REQUEST, 'invalid_payload');
        }

        if (isset($data['enableInApp']) && \is_bool($data['enableInApp'])) {
            $prefs->setEnableInApp($data['enableInApp']);
        }
        if (isset($data['enableEmailInstant']) && \is_bool($data['enableEmailInstant'])) {
            $prefs->setEnableEmailInstant($data['enableEmailInstant']);
        }
        if (isset($data['enableEmailDigest']) && \is_bool($data['enableEmailDigest'])) {
            $prefs->setEnableEmailDigest($data['enableEmailDigest']);
        }
        if (isset($data['notifyHabitReminder']) && \is_bool($data['notifyHabitReminder'])) {
            $prefs->setNotifyHabitReminder($data['notifyHabitReminder']);
        }
        if (isset($data['notifyWeeklyReview']) && \is_bool($data['notifyWeeklyReview'])) {
            $prefs->setNotifyWeeklyReview($data['notifyWeeklyReview']);
        }
        if (isset($data['notifyMonthlyReview']) && \is_bool($data['notifyMonthlyReview'])) {
            $prefs->setNotifyMonthlyReview($data['notifyMonthlyReview']);
        }
        if (isset($data['notifyGoalDeadline']) && \is_bool($data['notifyGoalDeadline'])) {
            $prefs->setNotifyGoalDeadline($data['notifyGoalDeadline']);
        }
        if (isset($data['notifyChallenge']) && \is_bool($data['notifyChallenge'])) {
            $prefs->setNotifyChallenge($data['notifyChallenge']);
        }
        if (isset($data['notifyConnection']) && \is_bool($data['notifyConnection'])) {
            $prefs->setNotifyConnection($data['notifyConnection']);
        }
        if (isset($data['notifyFeedback']) && \is_bool($data['notifyFeedback'])) {
            $prefs->setNotifyFeedback($data['notifyFeedback']);
        }
        if (isset($data['notifyPartnerInactive']) && \is_bool($data['notifyPartnerInactive'])) {
            $prefs->setNotifyPartnerInactive($data['notifyPartnerInactive']);
        }

        if (array_key_exists('weeklyReviewDay', $data)) {
            $day = $data['weeklyReviewDay'];
            if (!\is_int($day) || $day < 0 || $day > 6) {
                return $this->jsonError('Le jour de revue hebdomadaire doit être compris entre 0 (dimanche) et 6 (samedi).', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_review_day');
            }
            $prefs->setWeeklyReviewDay($day);
        }

        if (array_key_exists('preferredTime', $data)) {
            $time = trim((string) $data['preferredTime']);
            if (1 !== preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
                return $this->jsonError('L\'heure préférée doit être au format HH:MM (ex. 09:00).', Response::HTTP_UNPROCESSABLE_ENTITY, 'invalid_time_format');
            }
            $prefs->setPreferredTime($time);
        }

        $em->flush();

        return $this->jsonNoStore([
            'preferences' => $this->serializePreferences($prefs),
            'hasPushDevice' => $expo->hasDevice($user),
        ]);
    }

    #[Route('/essai', name: 'api_mobile_notifications_test', methods: ['POST'])]
    public function sendTestNotification(
        RateLimits $limits,
        ExpoPushSender $expo,
    ): JsonResponse {
        $user = $this->currentMobileUser();

        if (!$expo->hasDevice($user)) {
            return $this->jsonError('Aucun appareil mobile n\'est actuellement enregistré pour recevoir des notifications.', Response::HTTP_BAD_REQUEST, 'no_push_device');
        }

        if (!$limits->allow('push_test', (string) $user->getId())) {
            return $this->jsonError('Trop d\'essais récents. Vous pouvez envoyer au plus 3 notifications d\'essai par heure.', Response::HTTP_TOO_MANY_REQUESTS, 'rate_limited');
        }

        $sent = $expo->send(
            $user,
            'Notifications activées',
            'Vous recevrez ici vos rappels de rituels, votre revue du dimanche et vos défis.',
            '/dashboard',
            'bienvenue'
        );

        return $this->jsonNoStore([
            'ok' => true,
            'sent' => $sent,
        ]);
    }

    #[Route('', name: 'api_mobile_notifications_list', methods: ['GET'])]
    public function list(NotificationRepository $notifications): JsonResponse
    {
        $user = $this->currentMobileUser();
        $list = $notifications->findRecent($user, 50);
        $unread = $notifications->countUnread($user);

        $items = [];
        foreach ($list as $n) {
            $items[] = [
                'id' => $n->getId(),
                'type' => $n->getType()->value,
                'icon' => $n->getType()->icon(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'read' => $n->isRead(),
                'actionUrl' => $n->getActionUrl(),
                'createdAt' => $n->getCreatedAt()?->format(\DATE_ATOM),
            ];
        }

        return $this->jsonNoStore([
            'notifications' => $items,
            'unreadCount' => $unread,
        ]);
    }

    #[Route('/{id}/lu', name: 'api_mobile_notifications_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markRead(
        int $id,
        NotificationRepository $notifications,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user = $this->currentMobileUser();
        $notification = $notifications->find($id);

        if (null === $notification || $notification->getUser()->getId() !== $user->getId()) {
            return $this->jsonError('Notification introuvable.', Response::HTTP_NOT_FOUND, 'not_found');
        }

        if (!$notification->isRead()) {
            $notification->setRead(true);
            $em->flush();
        }

        return $this->jsonNoStore([
            'ok' => true,
            'unreadCount' => $notifications->countUnread($user),
        ]);
    }

    #[Route('/tout-lire', name: 'api_mobile_notifications_read_all', methods: ['POST'])]
    public function markAllRead(NotificationRepository $notifications): JsonResponse
    {
        $user = $this->currentMobileUser();
        $notifications->markAllRead($user);

        return $this->jsonNoStore([
            'ok' => true,
            'unreadCount' => 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePreferences(\App\Notification\Entity\NotificationPreference $p): array
    {
        return [
            'enableInApp' => $p->isEnableInApp(),
            'enableEmailInstant' => $p->isEnableEmailInstant(),
            'enableEmailDigest' => $p->isEnableEmailDigest(),
            'notifyHabitReminder' => $p->isNotifyHabitReminder(),
            'notifyWeeklyReview' => $p->isNotifyWeeklyReview(),
            'weeklyReviewDay' => $p->getWeeklyReviewDay(),
            'notifyMonthlyReview' => $p->isNotifyMonthlyReview(),
            'notifyGoalDeadline' => $p->isNotifyGoalDeadline(),
            'notifyChallenge' => $p->isNotifyChallenge(),
            'notifyConnection' => $p->isNotifyConnection(),
            'notifyFeedback' => $p->isNotifyFeedback(),
            'notifyPartnerInactive' => $p->isNotifyPartnerInactive(),
            'preferredTime' => $p->getPreferredTime(),
        ];
    }
}
