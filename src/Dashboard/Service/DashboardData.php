<?php

namespace App\Dashboard\Service;

use App\Compte\Entity\User;
use App\Dashboard\Repository\QuoteRepository;
use App\Personnalite\Entity\Assessment;
use App\Social\Entity\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;

/**
 * Données de l'accueil : citation du jour, raison d'être, rituels du jour, prochaines échéances.
 */
final class DashboardData
{
    /** Phrase affichée quand aucune citation n'est enregistrée. */
    public const FALLBACK_QUOTE = ['content' => 'Recommande à l\'Éternel tes œuvres, et tes projets réussiront.', 'author' => 'Proverbes 16:3'];

    public function __construct(
        private readonly QuoteRepository $quotes,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * Premiers pas après l'onboarding : ce qui est fait, et la prochaine chose à faire.
     * Retourne une liste vide quand tout est fait (la carte disparaît).
     *
     * @param Habit[] $habits
     *
     * @return list<array{label: string, hint: string, url: string, done: bool}>
     */
    public function nextSteps(User $user, array $habits): array
    {
        $plan = $user->getPlanDeVie();
        $steps = [
            ['label' => 'Poser vos fondations', 'hint' => 'Raison d\'être, valeurs et premier objectif.', 'url' => $this->urls->generate('app_plan_de_vie'),
                'done' => null !== $plan?->getPurpose()?->getStatement()],
            ['label' => 'Écrire votre vision', 'hint' => 'À quoi ressemblera votre vie quand elle sera alignée ? L\'assistant vous guide.', 'url' => $this->urls->generate('app_plan_wizard'),
                'done' => '' !== trim((string) $plan?->getVision()?->getContent())],
            ['label' => 'Choisir un premier rituel', 'hint' => 'Une petite action régulière qui nourrit votre objectif.', 'url' => $this->urls->generate('app_user_habits'),
                'done' => [] !== $habits],
            ['label' => 'Découvrir votre personnalité', 'hint' => 'Le profil complet : 15 minutes pour mieux vous connaître.', 'url' => $this->urls->generate('app_assessment_take', ['slug' => 'profil-complet']),
                'done' => $this->em->getRepository(Assessment::class)->count(['user' => $user]) > 0],
            ['label' => 'Inviter un partenaire de redevabilité', 'hint' => 'Quelqu\'un qui vous encourage et vous aide à tenir.', 'url' => $this->urls->generate('app_social'),
                'done' => $this->em->getRepository(Connection::class)->count(['requester' => $user]) + $this->em->getRepository(Connection::class)->count(['receiver' => $user]) > 0],
        ];

        foreach ($steps as $s) {
            if (!$s['done']) {
                return $steps;
            }
        }

        return [];
    }

    public function quote(): array
    {
        $quote = $this->quotes->findForDate(new \DateTimeImmutable('today'));

        return $quote ? ['content' => $quote->getContent(), 'author' => $quote->getAuthor()] : self::FALLBACK_QUOTE;
    }

    /** @param Habit[] $habits */
    public function stats(array $habits): array
    {
        $daily = array_filter($habits, fn (Habit $h) => HabitFrequency::DAILY === $h->getFrequency());

        return [
            'habitsToday' => [
                'done' => \count(array_filter($daily, fn (Habit $h) => $h->isCompletedForCurrentPeriod())),
                'total' => \count($daily),
            ],
        ];
    }

    /**
     * Prochaines échéances de tous les plans (objectifs, axes, projets, actions), à venir ou en retard de moins d'un mois.
     *
     * @return list<array{title: string, date: \DateTimeInterface, plan: string, route: string, overdue: bool}>
     */
    public function upcomingDeadlines(User $user, int $limit = 5): array
    {
        $items = [];
        $add = function (?string $title, ?\DateTimeInterface $date, string $plan, string $route) use (&$items) {
            if ($title && $date && $date >= new \DateTimeImmutable('-1 month')) {
                $items[] = ['title' => $title, 'date' => $date, 'plan' => $plan, 'route' => $route, 'overdue' => $date < new \DateTimeImmutable('today')];
            }
        };

        foreach ($user->getPlanDeVie()?->getLifeGoals() ?? [] as $g) {
            if ($g->getProgress() < 100) {
                $add($g->getTitle(), $g->getDeadline(), 'Plan de Vie', 'app_plan_de_vie');
            }
        }
        foreach ($user->getPlanDeCroissance()?->getGrowthGoals() ?? [] as $g) {
            $isDev = 'COMPETENCE' === $g->getAxis()->value;
            $add($g->getTitle(), $g->getDeadline(), $isDev ? 'Développement' : 'Croissance', $isDev ? 'app_plan_de_developpement' : 'app_plan_de_croissance');
            foreach ($g->getActions() as $a) {
                if ('DONE' !== $a->getStatus() && 'COMPLETED' !== $a->getStatus()) {
                    $add($a->getTitle(), $a->getDeadline(), $isDev ? 'Développement' : 'Croissance', $isDev ? 'app_plan_de_developpement' : 'app_plan_de_croissance');
                }
            }
        }
        foreach ($user->getPlanDeTransmission()?->getLegacyGoals() ?? [] as $g) {
            $add($g->getTitle(), $g->getDeadline(), 'Transmission', 'app_plan_de_transmission');
        }

        usort($items, fn ($a, $b) => $a['date'] <=> $b['date']);

        return \array_slice($items, 0, $limit);
    }
}
