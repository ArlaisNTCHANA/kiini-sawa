<?php

namespace App\Personnalite\Repository;

use App\Compte\Entity\User;
use App\Personnalite\Entity\TestAction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<TestAction> */
class TestActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TestAction::class);
    }

    /** @return array<string, list<string>> texte => cibles déjà choisies */
    public function addedBy(User $user): array
    {
        $out = [];
        foreach ($this->findBy(['user' => $user]) as $a) {
            $out[$a->getText()][] = $a->getTarget();
        }

        return $out;
    }

    /** @return list<TestAction> actions ajoutées il y a au moins 30 jours, pas encore relancées */
    public function dueForFollowUp(User $user, \DateTimeImmutable $today): array
    {
        return $this->createQueryBuilder('a')->where('a.user = :u')->andWhere('a.remindedAt IS NULL')->andWhere('a.createdAt <= :limit')
            ->setParameter('u', $user)->setParameter('limit', $today->modify('-30 days')->setTime(23, 59, 59))
            ->getQuery()->getResult();
    }
}
