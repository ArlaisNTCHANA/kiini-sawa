<?php

namespace App\Repository;

use App\Entity\GrowthGoalScore;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GrowthGoalScore>
 */
class GrowthGoalScoreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GrowthGoalScore::class);
    }
}
