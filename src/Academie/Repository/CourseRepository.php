<?php

namespace App\Academie\Repository;

use App\Academie\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Course> */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    /** @return list<Course> tous les parcours, dans l'ordre d'affichage. */
    public function allOrdered(): array
    {
        return $this->createQueryBuilder('c')->orderBy('c.sortOrder', 'ASC')->addOrderBy('c.id', 'ASC')->getQuery()->getResult();
    }

    /** @return list<Course> parcours publiés (catalogue). */
    public function published(): array
    {
        return $this->createQueryBuilder('c')->where('c.status = :s')->setParameter('s', Course::STATUS_PUBLISHED)
            ->orderBy('c.sortOrder', 'ASC')->addOrderBy('c.id', 'ASC')->getQuery()->getResult();
    }
}
