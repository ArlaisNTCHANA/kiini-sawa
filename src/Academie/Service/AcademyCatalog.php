<?php

namespace App\Academie\Service;

use App\Abonnement\Service\Entitlements;
use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\Lesson;
use App\Academie\Repository\CourseEnrollmentRepository;
use App\Academie\Repository\CourseRepository;
use App\Boussole\Repository\BoussoleResultRepository;
use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Ce que voit un membre dans l'Académie : le catalogue (parcours publiés, plus ceux qu'il a commencés même s'ils sont
 * repassés en brouillon), l'accès (gratuit ou PRO) et le parcours recommandé d'après sa dernière Boussole 360.
 */
final class AcademyCatalog
{
    /** Au-delà, on propose de refaire la Boussole 360 à la fin d'un parcours. */
    public const BOUSSOLE_REFRESH_DAYS = 30;

    public function __construct(
        private readonly CourseRepository $courses,
        private readonly CourseEnrollmentRepository $enrollments,
        private readonly BoussoleResultRepository $boussole,
        private readonly Entitlements $entitlements,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array{courses: list<Course>, enrollments: array<int, CourseEnrollment>} parcours visibles par ce membre,
     *                                                                              dans l'ordre d'affichage
     */
    public function visibleFor(User $user): array
    {
        $enrollments = $this->enrollments->byCourseFor($user);
        $courses = array_values(array_filter($this->courses->allOrdered(), fn (Course $c) => $c->isPublished() || isset($enrollments[$c->getId()])));

        return ['courses' => $courses, 'enrollments' => $enrollments];
    }

    /** Le parcours est-il visible (publié, ou déjà commencé) ? */
    public function isVisible(User $user, Course $course): bool
    {
        return $course->isPublished() || null !== $this->enrollments->findFor($user, $course);
    }

    /** Réservé à PRO et le membre ne l'est pas. */
    public function isLocked(User $user, Course $course): bool
    {
        return !$course->isFree() && !$this->entitlements->isPro($user);
    }

    public function enrollment(User $user, Course $course): ?CourseEnrollment
    {
        return $this->enrollments->findFor($user, $course);
    }

    /** Inscrit le membre au parcours à sa première leçon (une seule inscription par parcours). */
    public function enroll(User $user, Course $course): CourseEnrollment
    {
        $enrollment = $this->enrollments->findFor($user, $course);
        if (null === $enrollment) {
            $enrollment = new CourseEnrollment($user, $course);
            $this->em->persist($enrollment);
            $this->em->flush();
        }

        return $enrollment;
    }

    public function findLesson(Course $course, string $slug): ?Lesson
    {
        foreach ($course->getLessons(true) as $lesson) {
            if ($lesson->getSlug() === $slug) {
                return $lesson;
            }
        }

        return null;
    }

    /**
     * Parcours mis en avant d'après le plan prioritaire de la dernière Boussole 360 (le premier parcours publié de ce
     * plan), ou null.
     *
     * @param list<Course> $courses
     *
     * @return array{course: Course, plan: string}|null
     */
    public function recommended(User $user, array $courses): ?array
    {
        $result = $this->boussole->latestFor($user);
        if (null === $result) {
            return null;
        }
        foreach ($courses as $course) {
            if ($course->isPublished() && !$course->isCouple() && $course->getPrimaryPlan() === $result->getPriorityKey()) {
                return ['course' => $course, 'plan' => $result->getPriorityKey()];
            }
        }

        return null;
    }

    /** Proposer de refaire la Boussole 360 : jamais passée, ou il y a plus de 30 jours. */
    public function boussoleIsDue(User $user): bool
    {
        $result = $this->boussole->latestFor($user);

        return null === $result || $result->getCreatedAt() < new \DateTimeImmutable('-'.self::BOUSSOLE_REFRESH_DAYS.' days');
    }

    /** @return array{0: ?Lesson, 1: ?Lesson, 2: int, 3: int} leçon précédente, suivante, position (1…), total */
    public static function neighbours(Course $course, Lesson $lesson): array
    {
        $lessons = $course->getLessons(true);
        $index = array_search($lesson, $lessons, true);
        $index = false === $index ? 0 : $index;

        return [$lessons[$index - 1] ?? null, $lessons[$index + 1] ?? null, $index + 1, \count($lessons)];
    }
}
