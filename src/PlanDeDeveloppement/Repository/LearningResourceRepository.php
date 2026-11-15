<?php

namespace App\PlanDeDeveloppement\Repository;

use App\PlanDeDeveloppement\Entity\LearningResource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LearningResource>
 */
class LearningResourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearningResource::class);
    }
}
