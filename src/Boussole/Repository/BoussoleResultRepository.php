<?php

namespace App\Boussole\Repository;

use App\Boussole\Entity\BoussoleResult;
use App\Compte\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BoussoleResult>
 */
class BoussoleResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BoussoleResult::class);
    }

    public function latestFor(User $user): ?BoussoleResult
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<BoussoleResult> du plus récent au plus ancien */
    public function historyFor(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.user = :user')->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')->addOrderBy('b.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()->getResult();
    }

    /** Passation d'un utilisateur précis (null si elle appartient à quelqu'un d'autre). */
    public function findOneForUser(int $id, User $user): ?BoussoleResult
    {
        return $this->findOneBy(['id' => $id, 'user' => $user]);
    }
}
