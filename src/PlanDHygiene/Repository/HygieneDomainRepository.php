<?php

namespace App\PlanDHygiene\Repository;

use App\PlanDHygiene\Entity\HygieneDomain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HygieneDomain>
 */
class HygieneDomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HygieneDomain::class);
    }
}
