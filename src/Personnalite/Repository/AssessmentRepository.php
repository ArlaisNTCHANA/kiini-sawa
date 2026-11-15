<?php

namespace App\Personnalite\Repository;

use App\Compte\Entity\User;
use App\Personnalite\Entity\Assessment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Assessment>
 */
class AssessmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Assessment::class);
    }

    /** @return Assessment[] du plus récent au plus ancien */
    public function findForUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['takenAt' => 'DESC', 'id' => 'DESC']);
    }

    public function latest(User $user, string $instrument): ?Assessment
    {
        return $this->findOneBy(['user' => $user, 'instrument' => $instrument], ['takenAt' => 'DESC', 'id' => 'DESC']);
    }
}
