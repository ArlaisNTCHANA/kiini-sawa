<?php

namespace App\PlanDeDeveloppement\Repository;

use App\PlanDeDeveloppement\Entity\CareerStep;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CareerStep>
 */
class CareerStepRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CareerStep::class);
    }
}
