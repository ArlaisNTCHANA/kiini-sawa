<?php

namespace App\Feedback\Repository;

use App\Feedback\Entity\FeedbackQuestion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FeedbackQuestion>
 */
class FeedbackQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeedbackQuestion::class);
    }
}
