<?php

namespace App\Organisation\Repository;

use App\Organisation\Entity\Membership;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use App\Compte\Entity\User;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Membership>
 */
class MembershipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Membership::class);
    }

    /** @return Membership[] appartenances actives de l'utilisateur */
    public function findActiveFor(User $user): array
    {
        return $this->findBy(['user' => $user, 'active' => true]);
    }
}
