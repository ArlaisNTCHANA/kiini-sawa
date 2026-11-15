<?php

namespace App\PlanDHygiene\Repository;

use App\PlanDHygiene\Entity\PlanDHygiene;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanDHygiene>
 */
class PlanDHygieneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanDHygiene::class);
    }
}
