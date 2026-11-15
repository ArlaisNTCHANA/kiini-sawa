<?php

namespace App\PlanDeCroissance\Repository;

use App\PlanDeCroissance\Entity\ConfirmPoint;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConfirmPoint>
 */
class ConfirmPointRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConfirmPoint::class);
    }
}
