<?php

namespace App\Compte\Repository;

use App\Compte\Entity\AccountToken;
use App\Compte\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AccountToken>
 */
class AccountTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountToken::class);
    }

    public function findLatest(User $user, string $purpose): ?AccountToken
    {
        return $this->findOneBy(['user' => $user, 'purpose' => $purpose], ['id' => 'DESC']);
    }

    public function removeAll(User $user, string $purpose): void
    {
        $this->createQueryBuilder('t')
            ->delete()
            ->where('t.user = :user')
            ->andWhere('t.purpose = :purpose')
            ->setParameter('user', $user)
            ->setParameter('purpose', $purpose)
            ->getQuery()
            ->execute();
    }
}
