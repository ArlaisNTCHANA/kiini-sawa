<?php

namespace App\Academie\Repository;

use App\Academie\Entity\Course;
use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonNote;
use App\Compte\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<LessonNote> */
class LessonNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LessonNote::class);
    }

    /** @return list<LessonNote> notes du membre pour cette leçon, les plus récentes d'abord */
    public function forLesson(User $user, Lesson $lesson): array
    {
        return $this->createQueryBuilder('ln')->join('ln.note', 'n')->addSelect('n')
            ->where('n.user = :u')->andWhere('ln.lesson = :l')->setParameter('u', $user)->setParameter('l', $lesson)
            ->orderBy('ln.createdAt', 'DESC')->addOrderBy('ln.id', 'DESC')->getQuery()->getResult();
    }

    /** @return list<LessonNote> notes du membre pour tout un parcours, dans l'ordre d'écriture */
    public function forCourse(User $user, Course $course): array
    {
        return $this->createQueryBuilder('ln')->join('ln.note', 'n')->addSelect('n')
            ->join('ln.lesson', 'l')->join('l.module', 'm')
            ->where('n.user = :u')->andWhere('m.course = :c')->setParameter('u', $user)->setParameter('c', $course)
            ->orderBy('ln.createdAt', 'ASC')->getQuery()->getResult();
    }

    public function findOneForUser(int $id, User $user): ?LessonNote
    {
        return $this->createQueryBuilder('ln')->join('ln.note', 'n')
            ->where('ln.id = :id')->andWhere('n.user = :u')->setParameter('id', $id)->setParameter('u', $user)
            ->getQuery()->getOneOrNullResult();
    }
}
