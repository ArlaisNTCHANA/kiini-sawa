<?php

namespace App\PlanDeTransmission\Repository;

use App\PlanDeTransmission\Entity\TransmissionAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TransmissionAction>
 */
class TransmissionActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TransmissionAction::class);
    }
}
