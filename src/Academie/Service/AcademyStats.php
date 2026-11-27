<?php

namespace App\Academie\Service;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\LessonCompletion;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Suivi de l'Académie pour l'administrateur (27/11/2026) : qui commence, qui avance, où l'on s'arrête.
 *
 * Des totaux seulement, jamais un nom ni une réponse écrite : la page sert à améliorer les parcours, pas à surveiller
 * les membres (la fiche d'un membre montre déjà son avancement, à lui).
 */
final class AcademyStats
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Une ligne par parcours (publié ou non) : inscrits, actifs sur 7 jours, terminés, rythmes par e-mail.
     *
     * @return list<array{course: Course, enrolled: int, active: int, completed: int, paced: int, paused: int, rate: ?int}>
     */
    public function overview(): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(e.course) AS course',
                'COUNT(e.id) AS enrolled',
                'SUM(CASE WHEN e.lastActivityAt >= :week THEN 1 ELSE 0 END) AS active',
                'SUM(CASE WHEN e.completedAt IS NOT NULL THEN 1 ELSE 0 END) AS completed',
                "SUM(CASE WHEN e.paceMode <> 'FREE' AND e.pacePaused = false AND e.completedAt IS NULL THEN 1 ELSE 0 END) AS paced",
                "SUM(CASE WHEN e.paceMode <> 'FREE' AND e.pacePaused = true THEN 1 ELSE 0 END) AS paused")
            ->from(CourseEnrollment::class, 'e')->groupBy('e.course')
            ->setParameter('week', new \DateTimeImmutable('-7 days'))
            ->getQuery()->getArrayResult();
        $byCourse = array_column($rows, null, 'course');

        $out = [];
        foreach ($this->em->getRepository(Course::class)->findBy([], ['sortOrder' => 'ASC']) as $course) {
            $r = $byCourse[$course->getId()] ?? [];
            $enrolled = (int) ($r['enrolled'] ?? 0);
            $completed = (int) ($r['completed'] ?? 0);
            $out[] = [
                'course' => $course,
                'enrolled' => $enrolled,
                'active' => (int) ($r['active'] ?? 0),
                'completed' => $completed,
                'paced' => (int) ($r['paced'] ?? 0),
                'paused' => (int) ($r['paused'] ?? 0),
                'rate' => $enrolled > 0 ? (int) round($completed * 100 / $enrolled) : null,
            ];
        }
        usort($out, fn ($a, $b) => [$b['course']->isPublished(), $b['enrolled']] <=> [$a['course']->isPublished(), $a['enrolled']]);

        return $out;
    }

    /**
     * Séance par séance : combien l'ont faite, combien ont écrit dans leur plan, et la plus forte perte entre deux séances.
     *
     * @return array{enrolled: int, lessons: list<array{lesson: \App\Academie\Entity\Lesson, done: int, wrote: int, share: int, drop: int}>, worst: ?int, starts: list<array{label: string, n: int}>}
     */
    public function course(Course $course): array
    {
        $enrolled = (int) $this->em->getRepository(CourseEnrollment::class)->count(['course' => $course]);
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(c.lesson) AS lesson', 'COUNT(c.id) AS done', 'SUM(CASE WHEN c.createdEntityType IS NOT NULL THEN 1 ELSE 0 END) AS wrote')
            ->from(LessonCompletion::class, 'c')->join('c.enrollment', 'e')
            ->where('e.course = :course')->setParameter('course', $course)
            ->groupBy('c.lesson')->getQuery()->getArrayResult();
        $byLesson = array_column($rows, null, 'lesson');

        $lessons = [];
        $previous = $enrolled;
        $worst = null;
        $worstDrop = 0;
        foreach ($course->getLessons(true) as $i => $lesson) {
            $done = (int) ($byLesson[$lesson->getId()]['done'] ?? 0);
            $drop = max(0, $previous - $done);
            if ($drop > $worstDrop && $previous > 0) {
                $worstDrop = $drop;
                $worst = $i;
            }
            $lessons[] = [
                'lesson' => $lesson,
                'done' => $done,
                'wrote' => (int) ($byLesson[$lesson->getId()]['wrote'] ?? 0),
                'share' => $enrolled > 0 ? (int) round($done * 100 / $enrolled) : 0,
                'drop' => $drop,
            ];
            $previous = $done;
        }

        // Nouveaux inscrits des 8 dernières semaines (du plus ancien au plus récent)
        $starts = [];
        for ($w = 7; $w >= 0; --$w) {
            $from = new \DateTimeImmutable('monday this week -'.$w.' weeks midnight');
            $to = $from->modify('+7 days');
            $starts[] = ['label' => $from->format('d/m'), 'n' => (int) $this->em->createQueryBuilder()->select('COUNT(e.id)')->from(CourseEnrollment::class, 'e')
                ->where('e.course = :course AND e.startedAt >= :from AND e.startedAt < :to')
                ->setParameter('course', $course)->setParameter('from', $from)->setParameter('to', $to)->getQuery()->getSingleScalarResult()];
        }

        return ['enrolled' => $enrolled, 'lessons' => $lessons, 'worst' => $worst, 'starts' => $starts];
    }
}
