<?php

namespace App\Media\Repository;

use App\Compte\Entity\User;
use App\Media\Entity\Referral;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Referral> */
class ReferralRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Referral::class);
    }

    public function rewardedSince(User $referrer, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')
            ->where('r.referrer = :u')->andWhere('r.rewardedAt >= :since')
            ->setParameter('u', $referrer)->setParameter('since', $since)->getQuery()->getSingleScalarResult();
    }

    /** Récompenses déjà accordées à ce parrain pour des filleuls inscrits depuis la même adresse (empreinte). */
    public function rewardedFromSameIp(User $referrer, string $ipHash): int
    {
        return (int) $this->createQueryBuilder('r')->select('COUNT(r.id)')
            ->where('r.referrer = :u')->andWhere('r.rewardedAt IS NOT NULL')->andWhere('r.signupIpHash = :h')
            ->setParameter('u', $referrer)->setParameter('h', $ipHash)->getQuery()->getSingleScalarResult();
    }

    /** @return list<Referral> */
    public function forReferrer(User $referrer): array
    {
        return $this->createQueryBuilder('r')->join('r.referred', 'u')->addSelect('u')
            ->where('r.referrer = :u')->setParameter('u', $referrer)->orderBy('r.createdAt', 'DESC')->getQuery()->getResult();
    }
}
