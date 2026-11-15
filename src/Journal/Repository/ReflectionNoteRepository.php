<?php

namespace App\Journal\Repository;

use App\Journal\Entity\ReflectionNote;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReflectionNote>
 */
class ReflectionNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReflectionNote::class);
    }
}
