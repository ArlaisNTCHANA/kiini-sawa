<?php

namespace App\PlanDeCroissance\Repository;

use App\PlanDeCroissance\Entity\PlanDeCroissance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanDeCroissance>
 */
class PlanDeCroissanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanDeCroissance::class);
    }
}
