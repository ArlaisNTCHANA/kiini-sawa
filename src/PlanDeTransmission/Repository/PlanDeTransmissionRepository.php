<?php

namespace App\PlanDeTransmission\Repository;

use App\PlanDeTransmission\Entity\PlanDeTransmission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanDeTransmission>
 */
class PlanDeTransmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanDeTransmission::class);
    }
}
