<?php

namespace App\Bilan\Repository;

use App\Bilan\Entity\WeeklyReview;
use App\Compte\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WeeklyReview>
 */
class WeeklyReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WeeklyReview::class);
    }

    public function findForWeek(User $user, \DateTimeImmutable $weekStart): ?WeeklyReview
    {
        return $this->findOneBy(['user' => $user, 'weekStart' => $weekStart]);
    }

    /** @return WeeklyReview[] */
    public function findRecent(User $user, int $limit = 8): array
    {
        return $this->findBy(['user' => $user], ['weekStart' => 'DESC'], $limit);
    }
}
