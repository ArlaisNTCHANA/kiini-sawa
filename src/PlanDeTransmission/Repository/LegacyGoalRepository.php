<?php

namespace App\PlanDeTransmission\Repository;

use App\PlanDeTransmission\Entity\LegacyGoal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LegacyGoal>
 */
class LegacyGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LegacyGoal::class);
    }
}
