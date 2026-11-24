<?php

namespace App\Academie\Repository;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Compte\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CourseEnrollment> */
class CourseEnrollmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourseEnrollment::class);
    }

    public function findFor(User $user, Course $course): ?CourseEnrollment
    {
        return $this->findOneBy(['user' => $user, 'course' => $course]);
    }

    /** @return array<int, CourseEnrollment> inscriptions du membre, indexées par identifiant de parcours */
    public function byCourseFor(User $user): array
    {
        $out = [];
        foreach ($this->findBy(['user' => $user], ['lastActivityAt' => 'DESC']) as $e) {
            $out[$e->getCourse()->getId()] = $e;
        }

        return $out;
    }
}
