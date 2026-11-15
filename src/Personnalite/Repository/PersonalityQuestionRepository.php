<?php

namespace App\Personnalite\Repository;

use App\Personnalite\Entity\PersonalityQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PersonalityQuestion>
 */
class PersonalityQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PersonalityQuestion::class);
    }
}
