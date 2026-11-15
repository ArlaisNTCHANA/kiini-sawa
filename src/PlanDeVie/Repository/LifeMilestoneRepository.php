<?php

namespace App\PlanDeVie\Repository;

use App\PlanDeVie\Entity\LifeMilestone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LifeMilestone>
 */
class LifeMilestoneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LifeMilestone::class);
    }
}
