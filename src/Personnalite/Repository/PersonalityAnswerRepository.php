<?php

namespace App\Personnalite\Repository;

use App\Personnalite\Entity\PersonalityAnswer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonalityAnswer>
 */
class PersonalityAnswerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonalityAnswer::class);
    }
}
