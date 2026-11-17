<?php

namespace App\PlanDIntendance\Repository;

use App\Compte\Entity\User;
use App\PlanDIntendance\Entity\StewardshipItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StewardshipItem>
 */
class StewardshipItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StewardshipItem::class);
    }

    /** @return list<StewardshipItem> */
    public function forUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['position' => 'ASC', 'id' => 'ASC']);
    }

    public function countPriorities(User $user): int
    {
        return $this->count(['user' => $user, 'priority' => true]);
    }
}
