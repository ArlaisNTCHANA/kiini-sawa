<?php

namespace App\Notification\Service;

use App\Compte\Entity\User;
use App\Notification\Entity\Notification;
use App\Notification\Entity\NotificationPreference;
use App\Notification\Enum\NotificationType;
use App\Notification\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Point d'entrée unique pour prévenir un utilisateur : notification dans l'application
 * et, selon ses préférences, email.
 *
 * - Événements sociaux (demande de connexion…) : email seulement si « emails immédiats » est activé.
 * - Rappels (revue, rituels, échéances, partenaire) : email si « rappels par email » est activé.
 * - Chaque type peut être désactivé dans le profil ; une clé évite d'envoyer deux fois le même rappel.
 */
final class Notifier
{
    private const REMINDERS = [
        NotificationType::WEEKLY_REVIEW_REMINDER,
        NotificationType::MONTHLY_REVIEW_REMINDER,
        NotificationType::HABIT_REMINDER,
        NotificationType::GOAL_DEADLINE,
        NotificationType::PARTNER_INACTIVE,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $notifications,
        private readonly NotificationMailer $mailer,
        private readonly PushSender $push,
    ) {
    }

    /**
     * @param string|null          $key          identifiant du rappel (ex. « 2026-W46 ») : rien n'est envoyé s'il existe déjà
     * @param array{template?: string, context?: array<string, mixed>} $email gabarit d'email particulier (sinon le gabarit commun)
     *
     * @return bool true si l'utilisateur a été prévenu
     */
    public function notify(User $user, NotificationType $type, string $title, string $message, ?string $actionUrl = null, ?string $key = null, array $email = []): bool
    {
        $prefs = $this->preferences($user);
        if (null !== $key && $this->notifications->existsWithKey($user, $type, $key)) {
            return false;
        }
        if ($type->isTransactional()) {
            $inApp = true;
            $byEmail = true;
        } else {
            if (!$this->wants($prefs, $type)) {
                return false;
            }
            $inApp = $prefs->isEnableInApp();
            $byEmail = \in_array($type, self::REMINDERS, true) ? $prefs->isEnableEmailDigest() : $prefs->isEnableEmailInstant();
            // Accueil : un email (réglé par sa propre préférence), sans encombrer le centre de notifications
            if (\in_array($type, [NotificationType::ONBOARDING, NotificationType::CHALLENGE_DAY], true)) {
                $inApp = false;
                $byEmail = true;
            }
            // Rituels du matin : seulement sur le téléphone
            if (NotificationType::DAILY_RITUALS === $type) {
                $inApp = false;
                $byEmail = false;
            }
        }
        $byPush = $type->isPushable() && $this->push->hasDevice($user);
        if (!$inApp && !$byEmail && !$byPush) {
            return false;
        }

        // La notification est toujours enregistrée (elle sert aussi de trace anti-doublon) ; elle n'est pas affichée si l'utilisateur l'a désactivée
        $notification = (new Notification())->setUser($user)->setType($type)->setTitle($title)->setMessage($message)
            ->setActionUrl($actionUrl)->setMetadata($key)->setRead(!$inApp);
        $this->em->persist($notification);
        $this->em->flush();

        if ($byEmail) {
            $this->mailer->send($user, $type, $title, $message, $actionUrl, $email['template'] ?? null, $email['context'] ?? []);
        }
        if ($byPush) {
            $this->push->send($user, $title, $title === $message ? '' : $message, $actionUrl, $type->value.($key ? '-'.$key : ''));
        }

        return true;
    }

    public function preferences(User $user): NotificationPreference
    {
        $prefs = $user->getNotificationPreference();
        if (null === $prefs) {
            $prefs = new NotificationPreference();
            $user->setNotificationPreference($prefs);
            $this->em->persist($prefs);
            $this->em->flush();
        }

        return $prefs;
    }

    private function wants(NotificationPreference $p, NotificationType $type): bool
    {
        return match ($type) {
            NotificationType::CONNECTION_REQUEST, NotificationType::CONNECTION_ACCEPTED, NotificationType::COUPLE_SANCTUARY, NotificationType::ACCOUNTABILITY => $p->isNotifyConnection(),
            NotificationType::CHALLENGE_INVITE, NotificationType::CHALLENGE_ACCEPTED, NotificationType::CHALLENGE_DAY => $p->isNotifyChallenge(),
            NotificationType::FEEDBACK_RECEIVED => $p->isNotifyFeedback(),
            NotificationType::SUGGESTION_RECEIVED => $p->isNotifySuggestion(),
            NotificationType::COMMENT_RECEIVED => $p->isNotifyComment(),
            NotificationType::WEEKLY_REVIEW_REMINDER => $p->isNotifyWeeklyReview(),
            NotificationType::MONTHLY_REVIEW_REMINDER => $p->isNotifyMonthlyReview(),
            NotificationType::HABIT_REMINDER => $p->isNotifyHabitReminder(),
            NotificationType::GOAL_DEADLINE => $p->isNotifyGoalDeadline(),
            NotificationType::PARTNER_INACTIVE => $p->isNotifyPartnerInactive(),
            NotificationType::ONBOARDING => $p->isNotifyOnboarding(),
            NotificationType::DAILY_RITUALS => $p->isNotifyHabitReminder(),
            default => true,
        };
    }
}
