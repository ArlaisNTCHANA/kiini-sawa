<?php

namespace App\Organisation\Repository;

use App\Organisation\Entity\SeatOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SeatOrder>
 */
class SeatOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SeatOrder::class);
    }
}
