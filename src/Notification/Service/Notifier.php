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

    /**
     * Rappels qui cèdent leur e-mail au rendez-vous de l'Académie du jour (« un seul message par jour », parcours
     * guidés, 26/11/2026). Les messages importants (paiement, fin d'essai, redevabilité, retours des proches,
     * anniversaire) ne sont jamais retenus ; la notification dans l'application et sur le téléphone reste.
     */
    public const HELD_ON_SESSION_DAYS = [
        NotificationType::TIP,
        NotificationType::HABIT_REMINDER,
        NotificationType::WEEKLY_REVIEW_REMINDER,
        NotificationType::MONTHLY_REVIEW_REMINDER,
        NotificationType::CHALLENGE_DAY,
        NotificationType::PARTNER_INACTIVE,
        NotificationType::GOAL_DEADLINE,
    ];

    /** @var array<int, true> membres qui ont reçu aujourd'hui l'e-mail d'une séance ou d'une leçon */
    private array $sessionDay = [];

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
            // Accueil, défis, leçon de l'Académie, « Saviez-vous que… » (rythme choisi par le membre) : un email, sans encombrer le centre de notifications
            if (\in_array($type, [NotificationType::ONBOARDING, NotificationType::CHALLENGE_DAY, NotificationType::ACADEMY_LESSON, NotificationType::TIP], true)) {
                $inApp = false;
                $byEmail = true;
            }
            // Anniversaire : un e-mail une fois par an (le fondateur l'a voulu), coupé seulement par son interrupteur
            if (NotificationType::BIRTHDAY === $type) {
                $byEmail = true;
            }
            // Rituels du matin : seulement sur le téléphone
            if (NotificationType::DAILY_RITUALS === $type) {
                $inApp = false;
                $byEmail = false;
            }
        }
        // Le jour d'une séance, elle est le seul e-mail de rappel : les autres restent dans l'application
        if ($byEmail && isset($this->sessionDay[(int) $user->getId()]) && \in_array($type, self::HELD_ON_SESSION_DAYS, true)) {
            $byEmail = false;
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

    /**
     * Pour la tournée du jour (app:reminders:send) : ces membres ont reçu l'e-mail de leur séance, leurs autres
     * rappels ne partent pas par e-mail.
     *
     * @param int[] $userIds
     */
    public function holdRemindersForSessionDay(array $userIds): void
    {
        foreach ($userIds as $id) {
            $this->sessionDay[(int) $id] = true;
        }
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
            NotificationType::BIRTHDAY => $p->isNotifyBirthday(),
            NotificationType::TIP => $p->isNotifyTips(),
            default => true,
        };
    }
}
