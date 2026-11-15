<?php

namespace App\Notification\Service;

use App\Bilan\Entity\MonthlyReview;
use App\Bilan\Repository\WeeklyReviewRepository;
use App\Compte\Entity\User;
use App\Notification\Enum\NotificationType;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Rappels de rétention, calculés une fois par jour (commande app:reminders:send) :
 *
 * - revue hebdomadaire : le jour choisi par l'utilisateur, si la revue de la semaine n'est pas faite ;
 * - revue mensuelle : du 1er au 3 du mois, si la revue du mois précédent n'est pas faite ;
 * - rituels oubliés : aucun rituel quotidien coché depuis 3 jours (au plus une fois par semaine) ;
 * - échéances : objectif ou projet qui arrive à échéance dans 7 jours ;
 * - partenaire inactif : un partenaire sans activité depuis 7 jours (au plus une fois par semaine et par partenaire).
 *
 * Chaque rappel porte une clé : relancer la commande le même jour n'envoie rien de plus.
 */
final class ReminderService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Notifier $notifier,
        private readonly WeeklyReviewRepository $weeklyReviews,
    ) {
    }

    /** @return array<string, int> nombre de rappels envoyés par type */
    public function sendAll(\DateTimeImmutable $today): array
    {
        $sent = array_fill_keys(['revue hebdomadaire', 'revue mensuelle', 'rituels', 'échéances', 'partenaires', 'fin d\'essai ou d\'abonnement'], 0);
        $users = $this->em->getRepository(User::class)->findBy(['isVerified' => true]);
        foreach ($users as $user) {
            $sent['revue hebdomadaire'] += (int) $this->weeklyReview($user, $today);
            $sent['revue mensuelle'] += (int) $this->monthlyReview($user, $today);
            $sent['rituels'] += (int) $this->habits($user, $today);
            $sent['échéances'] += $this->deadlines($user, $today);
            $sent['partenaires'] += $this->partners($user, $today);
            $sent['fin d\'essai ou d\'abonnement'] += (int) $this->subscriptionEnding($user, $today);
        }

        return $sent;
    }

    public function weeklyReview(User $user, \DateTimeImmutable $today): bool
    {
        $prefs = $this->notifier->preferences($user);
        if ((int) $today->format('w') !== $prefs->getWeeklyReviewDay()) {
            return false;
        }
        $monday = $today->modify('monday this week')->setTime(0, 0);
        if ($this->weeklyReviews->findForWeek($user, $monday)) {
            return false;
        }

        return $this->notifier->notify($user, NotificationType::WEEKLY_REVIEW_REMINDER,
            'C\'est le moment de votre revue de la semaine',
            'Quinze minutes pour regarder vos victoires, évaluer vos plans et choisir vos 3 priorités de la semaine prochaine.',
            '/bilan/revue-hebdomadaire', 'semaine '.$monday->format('o-\WW'));
    }

    public function monthlyReview(User $user, \DateTimeImmutable $today): bool
    {
        if ((int) $today->format('j') > 3) {
            return false;
        }
        $previousMonth = $today->modify('first day of last month')->setTime(0, 0);
        $done = $this->em->getRepository(MonthlyReview::class)->findOneBy(['user' => $user, 'month' => \DateTime::createFromImmutable($previousMonth)]);
        if ($done) {
            return false;
        }

        return $this->notifier->notify($user, NotificationType::MONTHLY_REVIEW_REMINDER,
            'Votre revue du mois est prête',
            'Faites le point sur '.$this->monthName($previousMonth).' : ce qui a progressé, ce qui bloque, et la décision qui fera la différence le mois prochain.',
            '/bilan/revue-mensuelle', 'mois '.$previousMonth->format('Y-m'));
    }

    public function habits(User $user, \DateTimeImmutable $today): bool
    {
        $daily = array_filter($user->getHabits()->toArray(), fn ($h) => HabitFrequency::DAILY === $h->getFrequency());
        if ([] === $daily) {
            return false;
        }
        $since = $today->modify('-3 days')->setTime(0, 0);
        foreach ($daily as $habit) {
            if ($habit->getCreatedAt() > $since) {
                return false; // rituel tout récent : pas encore de relance
            }
            foreach ($habit->getLogs() as $log) {
                if ($log->isStatus() && $log->getDate() >= $since) {
                    return false;
                }
            }
        }

        return $this->notifier->notify($user, NotificationType::HABIT_REMINDER,
            'Vos rituels vous attendent',
            'Aucun rituel coché depuis 3 jours. Pas de culpabilité : reprenez simplement par le plus petit pas aujourd\'hui.',
            '/mes-plans/rituels', 'semaine '.$today->format('o-\WW'));
    }

    public function deadlines(User $user, \DateTimeImmutable $today): int
    {
        $target = $today->modify('+7 days')->format('Y-m-d');
        $items = [];
        foreach ($user->getPlanDeVie()?->getLifeGoals() ?? [] as $g) {
            if ($g->getDeadline()?->format('Y-m-d') === $target && $g->getProgress() < 100) {
                $items[] = ['LIFE_'.$g->getId(), $g->getTitle(), '/plan-de-vie'];
            }
        }
        foreach ($user->getPlanDeCroissance()?->getGrowthGoals() ?? [] as $g) {
            if ($g->getDeadline()?->format('Y-m-d') === $target && $g->getProgress() < 100) {
                $items[] = ['GROWTH_'.$g->getId(), $g->getTitle(), 'COMPETENCE' === $g->getAxis()->value ? '/plan-de-developpement' : '/plan-de-croissance'];
            }
        }
        foreach ($user->getPlanDeTransmission()?->getLegacyGoals() ?? [] as $g) {
            if ($g->getDeadline()?->format('Y-m-d') === $target && $g->getProgress() < 100) {
                $items[] = ['LEGACY_'.$g->getId(), $g->getTitle(), '/plan-de-transmission'];
            }
        }

        $sent = 0;
        foreach ($items as [$key, $title, $url]) {
            $sent += (int) $this->notifier->notify($user, NotificationType::GOAL_DEADLINE,
                'Échéance dans 7 jours : '.$title,
                'Votre objectif « '.$title.' » arrive à échéance le '.$today->modify('+7 days')->format('d/m/Y').'. Où en êtes-vous ? Ajustez la date ou planifiez le dernier pas.',
                $url, $key.' '.$target);
        }

        return $sent;
    }

    public function partners(User $user, \DateTimeImmutable $today): int
    {
        $sent = 0;
        $connections = array_merge($user->getConnections()->toArray(), $user->getConnectedTo()->toArray());
        foreach ($connections as $c) {
            if ('ACCEPTED' !== $c->getStatus()) {
                continue;
            }
            $partner = $c->getRequester() === $user ? $c->getReceiver() : $c->getRequester();
            $last = $partner?->getLastActiveAt();
            if (!$partner || null === $last || $last > $today->modify('-7 days')) {
                continue;
            }
            $sent += (int) $this->notifier->notify($user, NotificationType::PARTNER_INACTIVE,
                $partner->getDisplayName().' a besoin d\'un encouragement',
                $partner->getDisplayName().' ne s\'est pas connecté(e) depuis une semaine. Un petit message peut tout changer : c\'est le rôle d\'un partenaire de redevabilité.',
                '/social', 'partenaire '.$partner->getId().' '.$today->format('o-\WW'));
        }

        return $sent;
    }

    /**
     * Fin d'essai (3 jours avant) ou de période payée (7 jours avant) : les paiements manuels
     * ne se renouvellent pas, ce rappel est donc essentiel pour garder les abonnés.
     */
    public function subscriptionEnding(User $user, \DateTimeImmutable $today): bool
    {
        $sub = $user->getSubscription();
        if (null === $sub || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return false;
        }
        $isTrial = \App\Abonnement\Enum\SubscriptionStatus::TRIAL === $sub->getStatus();
        $end = $isTrial ? $sub->getTrialEndAt() : $sub->getCurrentPeriodEnd();
        if (null === $end || \App\Abonnement\Enum\SubscriptionTier::FREE === $sub->getTier()) {
            return false;
        }
        $daysLeft = (int) $today->setTime(0, 0)->diff(\DateTimeImmutable::createFromInterface($end)->setTime(0, 0))->format('%r%a');
        if ($daysLeft !== ($isTrial ? 3 : 7)) {
            return false;
        }

        return $isTrial
            ? $this->notifier->notify($user, NotificationType::TRIAL_ENDING, 'Votre essai PRO se termine dans 3 jours',
                'Le '.$end->format('d/m/Y').', vos plans de Croissance, Développement, Transmission et Hygiène passeront en lecture limitée. Gardez tout en choisissant une formule PRO, à partir de quelques centimes par jour.',
                '/abonnement', 'essai '.$end->format('Y-m-d'))
            : $this->notifier->notify($user, NotificationType::SUBSCRIPTION_ENDING, 'Votre abonnement PRO se termine dans 7 jours',
                'Votre accès PRO se termine le '.$end->format('d/m/Y').'. Pour continuer sans interruption, renouvelez dès maintenant : les jours restants sont conservés.',
                '/abonnement', 'fin '.$end->format('Y-m-d'));
    }

    private function monthName(\DateTimeImmutable $date): string
    {
        return (new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMMM y'))->format($date);
    }
}
