<?php

namespace App\Mobile\Repository;

use App\Compte\Entity\User;
use App\Mobile\Entity\MobileToken;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MobileToken>
 */
class MobileTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MobileToken::class);
    }

    public function findActiveByAccessHash(string $hash, \DateTimeImmutable $now): ?MobileToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->where('t.accessHash = :hash')
            ->andWhere('t.revokedAt IS NULL')
            ->andWhere('t.accessExpiresAt > :now')
            ->setParameter('hash', $hash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByRefreshHash(string $hash): ?MobileToken
    {
        return $this->createQueryBuilder('t')
            ->addSelect('u')
            ->join('t.user', 'u')
            ->where('t.refreshHash = :hash')
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<MobileToken>
     */
    public function findActiveForUser(User $user, \DateTimeImmutable $now): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.user = :user')
            ->andWhere('t.revokedAt IS NULL')
            ->andWhere('t.refreshExpiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('t.createdAt', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function revokeAllForUser(User $user, \DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('t')
            ->update()
            ->set('t.revokedAt', ':now')
            ->where('t.user = :user')
            ->andWhere('t.revokedAt IS NULL')
            ->setParameter('now', $now)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function purgeExpired(\DateTimeImmutable $before): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.revokedAt IS NOT NULL AND t.revokedAt < :before')
            ->orWhere('t.refreshExpiresAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
