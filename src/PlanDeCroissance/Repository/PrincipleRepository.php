<?php

namespace App\PlanDeCroissance\Repository;

use App\PlanDeCroissance\Entity\Principle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Principle>
 */
class PrincipleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Principle::class);
    }
}
