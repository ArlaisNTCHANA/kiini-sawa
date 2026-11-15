<?php

namespace App\PlanDeCroissance\Repository;

use App\PlanDeCroissance\Entity\GrowthAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GrowthAction>
 */
class GrowthActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GrowthAction::class);
    }
}
