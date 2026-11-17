<?php

namespace App\Methode\Repository;

use App\Methode\Entity\JourneyProgress;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JourneyProgress>
 */
class JourneyProgressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JourneyProgress::class);
    }
}
