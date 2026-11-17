<?php

namespace App\Bilan\Service;

use App\Bilan\Entity\WeeklyReview;
use App\Bilan\Repository\WeeklyReviewRepository;
use App\Compte\Entity\User;
use App\Journal\Entity\ReflectionNote;
use App\Journal\Enum\ReflectionType;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\GrowthGoalScore;
use App\PlanDeTransmission\Entity\LegacyGoal;
use App\PlanDHygiene\Entity\HygieneDomain;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Revue hebdomadaire sur les quatre plans stratégiques, les rituels et le journal
 * (reprise du WeeklyReviewWizard de la version précédente, étendu aux 4 plans).
 */
final class WeeklyReviewService
{
    /** Plans passés en revue, dans l'ordre d'affichage. */
    public const PLANS = [
        'CROISSANCE' => ['label' => 'Plan de Croissance', 'color' => '#D97706', 'icon' => 'zap'],
        'DEVELOPPEMENT' => ['label' => 'Plan de Développement personnel', 'color' => '#2563EB', 'icon' => 'graduation-cap'],
        'TRANSMISSION' => ['label' => 'Plan de Transmission', 'color' => '#DB2777', 'icon' => 'gift'],
        'HYGIENE' => ['label' => 'Plan d\'Hygiène de vie', 'color' => '#059669', 'icon' => 'shield'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WeeklyReviewRepository $reviews,
    ) {
    }

    /** Lundi de la semaine en cours. */
    public function currentWeekStart(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('monday this week');
    }

    /**
     * Éléments à évaluer, regroupés par plan.
     *
     * @return array<string, list<array{type: string, id: int, title: string, progress: ?int, entity: object}>>
     */
    public function items(User $user): array
    {
        $items = array_fill_keys(array_keys(self::PLANS), []);
        foreach ($user->getPlanDeCroissance()?->getGrowthGoals() ?? [] as $g) {
            $plan = 'COMPETENCE' === $g->getAxis()->value ? 'DEVELOPPEMENT' : 'CROISSANCE';
            $items[$plan][] = ['type' => 'GROWTH', 'id' => $g->getId(), 'title' => $g->getTitle(), 'progress' => $g->getProgress(), 'entity' => $g];
        }
        foreach ($user->getPlanDeTransmission()?->getLegacyGoals() ?? [] as $lg) {
            $items['TRANSMISSION'][] = ['type' => 'LEGACY', 'id' => $lg->getId(), 'title' => $lg->getTitle(), 'progress' => $lg->getProgress() ?? 0, 'entity' => $lg];
        }
        foreach ($user->getPlanDHygiene()?->getHygieneDomains() ?? [] as $hd) {
            $items['HYGIENE'][] = ['type' => 'HYGIENE', 'id' => $hd->getId(), 'title' => $hd->getName(), 'progress' => null, 'entity' => $hd];
        }

        return $items;
    }

    /**
     * Régularité des rituels sur les 7 derniers jours.
     *
     * @return list<array{title: string, done: int, expected: int}>
     */
    public function habitsThisWeek(User $user): array
    {
        $since = new \DateTimeImmutable('-6 days midnight');
        $rows = [];
        foreach ($user->getHabits() as $habit) {
            $done = 0;
            foreach ($habit->getLogs() as $log) {
                if ($log->isStatus() && $log->getDate() >= $since) {
                    ++$done;
                }
            }
            $expected = match ($habit->getFrequency()) {
                HabitFrequency::DAILY => 7,
                default => 1,
            };
            $rows[] = ['title' => $habit->getTitle(), 'done' => min($done, $expected), 'expected' => $expected];
        }

        return $rows;
    }

    public function journalEntriesThisWeek(User $user): int
    {
        $since = new \DateTimeImmutable('-6 days midnight');

        return \count(array_filter($user->getReflectionNotes()->toArray(), fn (ReflectionNote $n) => $n->getDate() >= $since));
    }

    /** Enregistre (ou complète) la revue de la semaine en cours. */
    public function save(User $user, InputBag $input): WeeklyReview
    {
        $week = $this->currentWeekStart();
        $review = $this->reviews->findForWeek($user, $week) ?? new WeeklyReview($user, $week);
        $text = fn (string $field) => ('' === ($v = trim($input->getString($field)))) ? null : mb_substr($v, 0, 5000);

        $review->setWins($text('wins'))->setStruggles($text('struggles'))->setLesson($text('lesson'));
        $review->setPriorities(array_values(array_filter(array_map(fn ($p) => mb_substr(trim((string) $p), 0, 255), $input->all('priorities')))));

        $scores = $input->all('score');
        $progress = $input->all('progress');
        $notes = $input->all('note');
        $saved = [];
        foreach ($this->items($user) as $plan => $planItems) {
            foreach ($planItems as $item) {
                $key = $item['type'].'_'.$item['id'];
                $score = isset($scores[$key]) && ctype_digit((string) $scores[$key]) ? max(1, min(10, (int) $scores[$key])) : null;
                $value = isset($progress[$key]) && is_numeric($progress[$key]) ? max(0, min(100, (int) $progress[$key])) : null;
                $note = trim((string) ($notes[$key] ?? '')) ?: null;
                if (null === $score && null === $note && (null === $value || $value === $item['progress'])) {
                    continue;
                }
                $this->applyToPlan($item['entity'], $score, $value, $note);
                $saved[] = ['plan' => $plan, 'type' => $item['type'], 'id' => $item['id'], 'title' => $item['title'], 'score' => $score, 'progress' => $value ?? $item['progress'], 'note' => $note];
            }
        }
        $review->setItems($saved);
        $review->touch();

        // La revue alimente aussi le journal, pour garder une trace lisible dans le temps
        if ($review->getWins() || $review->getLesson()) {
            $content = 'Revue de la semaine du '.$week->format('d/m/Y');
            foreach (['Victoires' => $review->getWins(), 'Difficultés' => $review->getStruggles(), 'Leçon' => $review->getLesson()] as $label => $value) {
                if ($value) {
                    $content .= "\n\n".$label.' : '.$value;
                }
            }
            if (null === $review->getId()) {
                $this->em->persist((new ReflectionNote())->setUser($user)->setContent($content)->setType(ReflectionType::INSIGHT)->setDate(new \DateTime()));
            }
        }

        $this->em->persist($review);
        $this->em->flush();

        return $review;
    }

    private function applyToPlan(object $entity, ?int $score, ?int $progress, ?string $note): void
    {
        if ($entity instanceof GrowthGoal) {
            if (null !== $progress) {
                $entity->setProgress($progress);
            }
            // Historique des évaluations, utilisé par les graphiques du Bilan
            if (null !== $score) {
                $this->em->persist((new GrowthGoalScore())->setGrowthGoal($entity)->setScore($score)->setProgressValue($entity->getProgress())->setNote($note)->setDate(new \DateTime()));
            }
        } elseif ($entity instanceof LegacyGoal && null !== $progress) {
            $entity->setProgress($progress);
        } elseif ($entity instanceof HygieneDomain) {
            // Pas de progression : seule l'évaluation de la semaine est conservée dans la revue
        }
    }
}
