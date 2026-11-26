<?php

namespace App\Academie\Service;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\Lesson;
use App\Academie\Repository\CourseEnrollmentRepository;
use App\Compte\Entity\User;
use App\Methode\Service\Journey;
use App\PlanDeVie\Service\PlanCatalog;

/**
 * Parcours guidés, lot 4 (26/11/2026) : le parcours de 90 jours enchaîne les six parcours de plan.
 * - Les six lignes : une ligne de métro par parcours de plan publié, avec la progression du membre.
 * - Les correspondances : pour chaque semaine du parcours de 90 jours, les séances guidées qui écrivent dans les mêmes
 *   rubriques que les écrans proposés cette semaine-là (même étape de l'assistant, même plan).
 */
final class PlanJourneyMap
{
    public function __construct(
        private readonly AcademyCatalog $catalog,
        private readonly CourseEnrollmentRepository $enrollments,
    ) {
    }

    /**
     * @return list<array{course: Course, plan: string, label: string, enrollment: ?CourseEnrollment, done: int, total: int, next: ?Lesson}>
     */
    public function lines(User $user): array
    {
        $mine = $this->enrollments->byCourseFor($user);
        $out = [];
        foreach ($this->catalog->planJourneys() as $course) {
            $enrollment = $mine[$course->getId()] ?? null;
            $lessons = $course->getLessons(true);
            $out[] = [
                'course' => $course,
                'plan' => (string) $course->getPrimaryPlan(),
                'label' => PlanCatalog::label((string) $course->getPrimaryPlan()),
                'enrollment' => $enrollment,
                'done' => null === $enrollment ? 0 : $enrollment->progress()['done'],
                'total' => \count($lessons),
                'next' => null === $enrollment ? ($lessons[0] ?? null) : $enrollment->nextLesson(),
            ];
        }

        return $out;
    }

    /**
     * Séances guidées de chaque semaine du parcours de 90 jours.
     *
     * @return array<int, list<array{course: Course, lesson: Lesson, done: bool}>>
     */
    public function sessionsByWeek(User $user): array
    {
        $mine = $this->enrollments->byCourseFor($user);
        $out = [];
        foreach (Journey::PLAN as $week => $def) {
            $targets = [];
            foreach ($def['actions'] as $action) {
                if ('app_plan_wizard' === $action[1] && isset($action[2]['step'])) {
                    $targets[] = [(string) ($action[2]['plan'] ?? 'VIE'), (int) $action[2]['step']];
                }
                if ('app_plan_d_intendance' === $action[1]) {
                    $targets[] = ['INTENDANCE', null];
                }
            }
            $out[$week] = [];
            foreach ($this->catalog->planJourneys() as $course) {
                foreach ($course->getLessons(true) as $lesson) {
                    $rubric = PlanRubrics::RUBRICS[(string) $lesson->getTargetAction()] ?? null;
                    if (null !== $rubric && \in_array([$rubric['plan'], $rubric['step']], $targets, true)) {
                        $enrollment = $mine[$course->getId()] ?? null;
                        $out[$week][] = ['course' => $course, 'lesson' => $lesson, 'done' => null !== $enrollment && $enrollment->hasCompleted($lesson)];
                    }
                }
            }
        }

        return $out;
    }
}
