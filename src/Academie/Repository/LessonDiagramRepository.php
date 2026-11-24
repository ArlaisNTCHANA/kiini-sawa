<?php

namespace App\Academie\Repository;

use App\Academie\Entity\LessonDiagram;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LessonDiagram> */
class LessonDiagramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonDiagram::class);
    }

    /** @return list<LessonDiagram> */
    public function allOrdered(): array
    {
        return $this->findBy([], ['title' => 'ASC']);
    }
}
