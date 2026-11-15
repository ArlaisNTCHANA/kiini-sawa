<?php

namespace App\Repository;

use App\Entity\Habit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Habit>
 */
class HabitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Habit::class);
    }

    /**
     * @return Habit[]
     */
    public function findByWithAllRelations($user): array
    {
        return $this->createQueryBuilder('h')
            ->select('h', 'l', 'gg', 'sg', 'lg', 'hd', 'lig')
            ->leftJoin('h.logs', 'l')
            ->leftJoin('h.growthGoal', 'gg')
            ->leftJoin('h.skillGoal', 'sg')
            ->leftJoin('h.legacyGoal', 'lg')
            ->leftJoin('h.hygieneDomain', 'hd')
            ->leftJoin('h.lifeGoal', 'lig')
            ->where('h.user = :user')
            ->setParameter('user', $user)
            ->orderBy('h.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

