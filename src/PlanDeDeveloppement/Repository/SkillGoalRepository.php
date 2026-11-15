<?php

namespace App\PlanDeDeveloppement\Repository;

use App\PlanDeDeveloppement\Entity\SkillGoal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SkillGoal>
 */
class SkillGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SkillGoal::class);
    }
}
