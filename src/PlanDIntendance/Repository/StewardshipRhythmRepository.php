<?php

namespace App\PlanDIntendance\Repository;

use App\PlanDIntendance\Entity\StewardshipRhythm;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StewardshipRhythm>
 */
class StewardshipRhythmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StewardshipRhythm::class);
    }
}
