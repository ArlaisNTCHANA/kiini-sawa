<?php

namespace App\Rapide\Repository;

use App\Compte\Entity\User;
use App\Rapide\Entity\SyncOperation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SyncOperation> */
class SyncOperationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncOperation::class);
    }

    public function seen(User $user, string $uid): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'uid' => $uid]);
    }

    /** Ménage : les identifiants de plus de 60 jours ne servent plus (un téléphone n'attend pas si longtemps). */
    public function purgeOlderThan(\DateTimeImmutable $limit): int
    {
        return (int) $this->createQueryBuilder('o')->delete()->where('o.appliedAt < :limit')->setParameter('limit', $limit)->getQuery()->execute();
    }
}
