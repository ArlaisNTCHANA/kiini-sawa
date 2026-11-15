<?php

namespace App\Couple\Repository;

use App\Compte\Entity\User;
use App\Couple\Entity\Couple;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Couple>
 */
class CoupleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Couple::class);
    }

    /** Espace couple de l'utilisateur (actif ou en attente qu'il a créé). */
    public function findFor(User $user): ?Couple
    {
        return $this->createQueryBuilder('c')
            ->where('c.partnerA = :u OR c.partnerB = :u')->setParameter('u', $user)
            ->orderBy('c.id', 'DESC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
    }
}
