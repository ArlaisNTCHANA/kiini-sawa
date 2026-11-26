<?php

namespace App\Dashboard\Service;

use App\Compte\Entity\User;
use App\Dashboard\Repository\QuoteRepository;
use App\Personnalite\Entity\Assessment;
use App\Personnalite\Psychometrie\Instruments;
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
        private readonly \App\Abonnement\Service\Entitlements $entitlements,
        private readonly \App\Academie\Service\AcademyCatalog $academy,
        private readonly \App\Social\Service\PartnerDirectory $partners,
    ) {
    }

    /**
     * Premiers pas après l'onboarding : ce qui est fait, et la prochaine chose à faire.
     * Retourne une liste vide quand tout est fait (la carte disparaît).
     *
     * @param Habit[] $habits
     *
     * Parcours guidés (décision du fondateur du 26/11/2026) : quand le parcours « Mon Plan de Vie » est publié, il
     * remplace « Poser vos fondations » et « Écrire votre vision » (un seul premier pas, avec sa station). Les
     * assistants restent le mode libre, pour compléter et corriger.
     *
     * @return list<array{key: string, label: string, hint: string, url: string, route: string, params: array, done: bool, cta: string}>
     */
    public function nextSteps(User $user, array $habits): array
    {
        $plan = $user->getPlanDeVie();
        $foundations = null !== $plan?->getPurpose()?->getStatement();
        $vision = '' !== trim((string) $plan?->getVision()?->getContent());
        $firstTest = $this->entitlements->canTakeTest($user, 'profil-complet') ? 'profil-complet' : $this->entitlements->firstFreeTest();
        $step = fn (string $key, string $label, string $hint, string $route, array $params, bool $done, string $cta = 'Commencer') => [
            'key' => $key, 'label' => $label, 'hint' => $hint, 'url' => $this->urls->generate($route, $params), 'route' => $route, 'params' => $params, 'done' => $done, 'cta' => $cta,
        ];

        $journey = null;
        foreach ($this->academy->planJourneys() as $course) {
            if ('VIE' === $course->getPrimaryPlan()) {
                $journey = $course;
                break;
            }
        }
        if (null !== $journey) {
            $enrollment = $this->academy->enrollment($user, $journey);
            $lessons = $journey->getLessons(true);
            $next = $enrollment?->nextLesson() ?? ($lessons[0] ?? null);
            $progress = $enrollment?->progress() ?? ['done' => 0, 'total' => \count($lessons)];
            $started = null !== $enrollment && $progress['done'] > 0;
            $first = [$step('journey', 'Poser votre Plan de Vie, séance après séance',
                $started && null !== $next
                    ? sprintf('Station %d sur %d : « %s », %d min.', $progress['done'] + 1, $progress['total'], $next->getTitle(), $next->getDurationMinutes())
                    : sprintf('%d courtes séances, une par jour : votre But de Dieu, vos valeurs, votre vision et vos objectifs, écrits directement dans votre plan.', $progress['total']),
                null !== $next ? 'app_academie_lesson' : 'app_academie_course',
                null !== $next ? ['slug' => $journey->getSlug(), 'lessonSlug' => $next->getSlug()] : ['slug' => $journey->getSlug()],
                (bool) $enrollment?->isCompleted() || ($foundations && $vision), $started ? 'Reprendre' : 'Commencer')];
        } else {
            $first = [
                $step('foundations', 'Poser vos fondations', 'Raison d\'être, valeurs et premier objectif.', 'app_plan_de_vie', [], $foundations),
                $step('vision', 'Écrire votre vision', 'À quoi ressemblera votre vie quand elle sera alignée ? L\'assistant vous guide.', 'app_plan_wizard', [], $vision),
            ];
        }
        $steps = [...$first,
            $step('habit', 'Choisir un premier rituel', 'Une petite action régulière qui nourrit votre objectif.', 'app_user_habits', [], [] !== $habits),
            $step('test', 'Découvrir votre personnalité', sprintf('Le test « %s » : %d minutes pour mieux vous connaître.', Instruments::CATALOG[$firstTest]['name'], Instruments::CATALOG[$firstTest]['minutes']), 'app_assessment_take', ['slug' => $firstTest],
                $this->em->getRepository(Assessment::class)->count(['user' => $user]) > 0),
            $step('partner', 'Inviter un Partenaire de Redevabilité', 'Quelqu\'un qui vous encourage et vous aide à tenir.', 'app_partner_invite', [],
                $this->partners->hasAny($user)),
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
