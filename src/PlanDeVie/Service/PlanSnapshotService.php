<?php

namespace App\PlanDeVie\Service;

use App\Compte\Entity\User;
use App\PlanDeVie\Entity\PlanHistory;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Versions datées des plans : une photographie lisible de l'état des 5 plans à une date.
 * Créée à chaque revue mensuelle, après un passage complet dans l'assistant (une par jour au plus)
 * ou à la demande depuis la page Historique.
 */
final class PlanSnapshotService
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function snapshot(User $user, string $title): PlanHistory
    {
        $history = (new PlanHistory())->setUser($user)->setTitle(mb_substr($title, 0, 255))
            ->setData(json_encode($this->capture($user), \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR));
        $this->em->persist($history);
        $this->em->flush();

        return $history;
    }

    /** Version automatique : au plus une par jour. */
    public function snapshotOncePerDay(User $user, string $title): void
    {
        foreach ($user->getPlanHistories() as $h) {
            if ($h->getCreatedAt() >= new \DateTimeImmutable('today')) {
                return;
            }
        }
        $this->snapshot($user, $title);
    }

    public function capture(User $user): array
    {
        $plan = $user->getPlanDeVie();
        $values = ['real' => [], 'declared' => [], 'counter' => []];
        foreach ($plan?->getValues() ?? [] as $v) {
            $values[$v->getCategory() ?: 'real'][] = $v->getName();
        }
        $balance = ['CONVICTION' => [], 'LIMITING_BELIEF' => [], 'TALENT' => []];
        foreach ($user->getInternalBalances() as $ib) {
            $balance[$ib->getType()][] = $ib->getText();
        }
        $goals = [];
        foreach ($plan?->getLifeGoals() ?? [] as $g) {
            $goals[$g->getCategory()][] = ['title' => $g->getTitle(), 'deadline' => $g->getDeadline()?->format('Y-m-d'), 'progress' => $g->getProgress()];
        }
        $axes = [];
        foreach ($user->getPlanDeCroissance()?->getGrowthGoals() ?? [] as $g) {
            $axes[$g->getAxis()->value][] = ['title' => $g->getTitle(), 'strategy' => $g->getStrategy(), 'progress' => $g->getProgress()];
        }

        return [
            'purpose' => $plan?->getPurpose()?->getStatement(),
            'vision' => $plan?->getVision()?->getContent(),
            'values' => $values,
            'convictions' => $balance['CONVICTION'],
            'limitingBeliefs' => $balance['LIMITING_BELIEF'],
            'talents' => $balance['TALENT'],
            'passions' => array_map(fn ($p) => $p->getName(), $plan?->getPassions()->toArray() ?? []),
            'lifeGoals' => $goals,
            'growth' => $axes,
            'legacy' => array_map(fn ($l) => ['title' => $l->getTitle(), 'progress' => $l->getProgress()], $user->getPlanDeTransmission()?->getLegacyGoals()->toArray() ?? []),
            'hygiene' => array_map(fn ($h) => $h->getName(), $user->getPlanDHygiene()?->getHygieneDomains()->toArray() ?? []),
        ];
    }
}
