<?php

namespace App\Notification\Enum;

enum NotificationType: string
{
    case CONNECTION_REQUEST = 'CONNECTION_REQUEST';
    case CONNECTION_ACCEPTED = 'CONNECTION_ACCEPTED';
    case CHALLENGE_INVITE = 'CHALLENGE_INVITE';
    case CHALLENGE_ACCEPTED = 'CHALLENGE_ACCEPTED';
    case FEEDBACK_RECEIVED = 'FEEDBACK_RECEIVED';
    case SUGGESTION_RECEIVED = 'SUGGESTION_RECEIVED';
    case COMMENT_RECEIVED = 'COMMENT_RECEIVED';
    // Rappels (phase 4)
    case WEEKLY_REVIEW_REMINDER = 'WEEKLY_REVIEW_REMINDER';
    case MONTHLY_REVIEW_REMINDER = 'MONTHLY_REVIEW_REMINDER';
    case HABIT_REMINDER = 'HABIT_REMINDER';
    case GOAL_DEADLINE = 'GOAL_DEADLINE';
    case PARTNER_INACTIVE = 'PARTNER_INACTIVE';
    // Abonnement (phase 5) : messages de service, toujours envoyés
    case PAYMENT_DECLARED = 'PAYMENT_DECLARED';
    case PAYMENT_VALIDATED = 'PAYMENT_VALIDATED';
    case PAYMENT_REJECTED = 'PAYMENT_REJECTED';
    case TRIAL_ENDING = 'TRIAL_ENDING';
    case SUBSCRIPTION_ENDING = 'SUBSCRIPTION_ENDING';
    // Accueil : conseils des deux premières semaines (désactivables)
    case ONBOARDING = 'ONBOARDING';

    /** Icône Lucide affichée dans le centre de notifications. */
    public function icon(): string
    {
        return match ($this) {
            self::CONNECTION_REQUEST, self::CONNECTION_ACCEPTED, self::PARTNER_INACTIVE => 'users',
            self::CHALLENGE_INVITE, self::CHALLENGE_ACCEPTED => 'trophy',
            self::FEEDBACK_RECEIVED, self::COMMENT_RECEIVED => 'message-circle',
            self::SUGGESTION_RECEIVED => 'lightbulb',
            self::WEEKLY_REVIEW_REMINDER, self::MONTHLY_REVIEW_REMINDER => 'calendar-check',
            self::HABIT_REMINDER => 'repeat',
            self::GOAL_DEADLINE => 'flag',
            self::PAYMENT_DECLARED, self::PAYMENT_VALIDATED, self::PAYMENT_REJECTED => 'credit-card',
            self::TRIAL_ENDING, self::SUBSCRIPTION_ENDING => 'hourglass',
            self::ONBOARDING => 'sparkles',
        };
    }

    /** Messages de service (paiement, fin d'essai) : envoyés quelles que soient les préférences. */
    public function isTransactional(): bool
    {
        return \in_array($this, [self::PAYMENT_DECLARED, self::PAYMENT_VALIDATED, self::PAYMENT_REJECTED, self::TRIAL_ENDING, self::SUBSCRIPTION_ENDING], true);
    }
}
