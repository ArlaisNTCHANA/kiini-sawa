<?php

namespace App\PlanDeDeveloppement\Repository;

use App\PlanDeDeveloppement\Entity\PlanDeDeveloppementPersonnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PlanDeDeveloppementPersonnel>
 */
class PlanDeDeveloppementPersonnelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanDeDeveloppementPersonnel::class);
    }
}
