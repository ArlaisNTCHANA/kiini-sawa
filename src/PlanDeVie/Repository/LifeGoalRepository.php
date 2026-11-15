<?php

namespace App\PlanDeVie\Repository;

use App\PlanDeVie\Entity\LifeGoal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LifeGoal>
 */
class LifeGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LifeGoal::class);
    }
}
