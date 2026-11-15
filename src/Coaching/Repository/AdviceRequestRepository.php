<?php

namespace App\Coaching\Repository;

use App\Coaching\Entity\AdviceRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdviceRequest>
 */
class AdviceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdviceRequest::class);
    }
}
