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
    // Défis collectifs : inscription et action du jour (désactivables avec les autres notifications de défis)
    case CHALLENGE_DAY = 'CHALLENGE_DAY';
    // Parrainage : un ami parrainé a rejoint KIINI SAWA (message de service : un mois PRO offert)
    case REFERRAL_REWARD = 'REFERRAL_REWARD';
    // Rappel du matin « vos rituels du jour » : notification sur le téléphone uniquement
    case DAILY_RITUALS = 'DAILY_RITUALS';
    // Sanctuaire du couple : défi partagé, méditation et ping de connexion
    case COUPLE_SANCTUARY = 'COUPLE_SANCTUARY';
    // Engagement de redevabilité : accord ou réponse du partenaire
    case ACCOUNTABILITY = 'ACCOUNTABILITY';
    // Académie : leçon du jour selon le rythme choisi par le membre (et message de pause automatique)
    case ACADEMY_LESSON = 'ACADEMY_LESSON';
    // Anniversaire : une nouvelle année de vie, invitation à relire son Plan de Vie (désactivable)
    case BIRTHDAY = 'BIRTHDAY';
    // « Saviez-vous que… » : une fonction pas encore utilisée, par e-mail, au plus toutes les deux semaines (désactivable)
    case TIP = 'TIP';

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
            self::CHALLENGE_DAY => 'trophy',
            self::REFERRAL_REWARD => 'gift',
            self::DAILY_RITUALS => 'sun',
            self::COUPLE_SANCTUARY => 'heart',
            self::ACCOUNTABILITY => 'shield-check',
            self::ACADEMY_LESSON => 'graduation-cap',
            self::BIRTHDAY => 'cake',
            self::TIP => 'lightbulb',
        };
    }

    /** Types aussi envoyés sur le téléphone (si l'utilisateur l'a autorisé sur au moins un appareil). */
    public function isPushable(): bool
    {
        return \in_array($this, [self::WEEKLY_REVIEW_REMINDER, self::MONTHLY_REVIEW_REMINDER, self::HABIT_REMINDER, self::GOAL_DEADLINE,
            self::CHALLENGE_DAY, self::DAILY_RITUALS, self::REFERRAL_REWARD, self::FEEDBACK_RECEIVED, self::CONNECTION_REQUEST,
            self::CONNECTION_ACCEPTED, self::TRIAL_ENDING, self::SUBSCRIPTION_ENDING, self::PAYMENT_VALIDATED, self::COUPLE_SANCTUARY, self::ACCOUNTABILITY, self::BIRTHDAY], true);
    }

    /** Messages de service (paiement, fin d'essai) : envoyés quelles que soient les préférences. */
    public function isTransactional(): bool
    {
        return \in_array($this, [self::PAYMENT_DECLARED, self::PAYMENT_VALIDATED, self::PAYMENT_REJECTED, self::TRIAL_ENDING, self::SUBSCRIPTION_ENDING, self::REFERRAL_REWARD], true);
    }
}
