<?php

namespace App\PlanDeTransmission\Repository;

use App\PlanDeTransmission\Entity\TalentDon;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TalentDon>
 */
class TalentDonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TalentDon::class);
    }
}
