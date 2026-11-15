<?php

namespace App\Dashboard\Service;

use App\Compte\Entity\User;
use App\Dashboard\Repository\QuoteRepository;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;

/**
 * Données de l'accueil : citation du jour, raison d'être, rituels du jour, prochaines échéances.
 */
final class DashboardData
{
    /** Phrase affichée quand aucune citation n'est enregistrée. */
    public const FALLBACK_QUOTE = ['content' => 'Recommande à l\'Éternel tes œuvres, et tes projets réussiront.', 'author' => 'Proverbes 16:3'];

    public function __construct(private readonly QuoteRepository $quotes)
    {
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
