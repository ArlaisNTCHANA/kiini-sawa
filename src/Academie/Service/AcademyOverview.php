<?php

namespace App\Academie\Service;

use App\Academie\Entity\Course;
use App\Academie\Entity\Lesson;
use App\PlanDeVie\Service\PlanCatalog;

/**
 * Ce qu'un parcours fait travailler, pour sa carte et sa page de présentation (et l'API mobile) :
 *
 * - **les plans couverts** : le plan principal, les plans où écrivent les exercices des leçons visibles, et ceux que
 *   l'auteur a ajoutés à la main dans le studio (Course::extraPlans) ; toujours dans l'ordre du livre ;
 * - **le quotidien** : exercices qui vont dans les Rituels ou le Journal (ce ne sont pas des plans) ;
 * - **le Sanctuaire du Couple**, toujours à part (ce n'est pas un 7e plan, docs/lexique.md) ;
 * - quelques chiffres (leçons, minutes, questions, exercices, vidéos).
 */
final class AcademyOverview
{
    /** Les 6 plans, dans l'ordre du livre, et leur nombre d'exercices dans ce parcours.
     *
     * @return list<array{key: string, label: string, short: string, question: string, icon: string, color: string, soft: string, main: bool, covered: bool, exercises: int}>
     */
    public static function plans(Course $course): array
    {
        $counts = self::targetCounts($course);
        $extra = $course->getExtraPlans();
        $out = [];
        foreach (PlanCatalog::PLANS as $key => $plan) {
            $look = AcademyPlaces::look($key);
            $main = $course->getPrimaryPlan() === $key;
            $exercises = $counts[$key] ?? 0;
            $out[] = [
                'key' => $key,
                'label' => $plan['label'],
                'short' => self::short($plan['label']),
                'question' => $plan['question'],
                'icon' => $look['icon'],
                'color' => $look['color'],
                'soft' => $look['soft'],
                'main' => $main,
                'covered' => $main || $exercises > 0 || \in_array($key, $extra, true),
                'exercises' => $exercises,
            ];
        }

        return $out;
    }

    /** Les plans couverts seulement, le plan principal en premier (pastilles des cartes).
     *
     * @return list<array{key: string, label: string, short: string, question: string, icon: string, color: string, soft: string, main: bool, covered: bool, exercises: int}>
     */
    public static function coveredPlans(Course $course): array
    {
        $covered = array_values(array_filter(self::plans($course), fn (array $p) => $p['covered']));
        usort($covered, fn (array $a, array $b) => (int) $b['main'] <=> (int) $a['main']);

        return $covered;
    }

    /** @return array{rituels: int, journal: int, couple: int} exercices hors des 6 plans */
    public static function daily(Course $course): array
    {
        $counts = self::targetCounts($course);

        return ['rituels' => $counts['RITUEL'] ?? 0, 'journal' => $counts['JOURNAL'] ?? 0, 'couple' => $counts['COUPLE'] ?? 0];
    }

    /** @return array{modules: int, lessons: int, minutes: int, questions: int, exercises: int, videos: int, min_lesson: int, max_lesson: int} */
    public static function stats(Course $course): array
    {
        $lessons = $course->getLessons(true);
        $durations = array_map(fn (Lesson $l) => $l->getDurationMinutes(), $lessons);
        $modules = 0;
        foreach ($course->getModules() as $module) {
            foreach ($module->getLessons() as $lesson) {
                if ($lesson->isVisible()) {
                    ++$modules;
                    break;
                }
            }
        }

        return [
            'modules' => $modules,
            'lessons' => \count($lessons),
            'minutes' => array_sum($durations),
            'questions' => array_sum(array_map(fn (Lesson $l) => \count($l->getQuestions()), $lessons)),
            'exercises' => \count(array_filter($lessons, fn (Lesson $l) => $l->hasPractice())),
            'videos' => \count(array_filter($lessons, fn (Lesson $l) => $l->hasVideo())),
            'min_lesson' => [] === $durations ? 0 : min($durations),
            'max_lesson' => [] === $durations ? 0 : max($durations),
        ];
    }

    /**
     * Le programme : les modules qui ont au moins une leçon visible, numérotés (étapes), et leurs leçons visibles
     * numérotées de 1 à N sur tout le parcours.
     *
     * @return list<array{step: int, title: string, minutes: int, lessons: list<array{n: int, lesson: Lesson}>}>
     */
    public static function programme(Course $course): array
    {
        $out = [];
        $n = 0;
        foreach ($course->getModules() as $module) {
            $lessons = [];
            foreach ($module->getLessons() as $lesson) {
                if ($lesson->isVisible()) {
                    $lessons[] = ['n' => ++$n, 'lesson' => $lesson];
                }
            }
            if ([] !== $lessons) {
                $out[] = ['step' => \count($out) + 1, 'title' => $module->getTitle(), 'lessons' => $lessons,
                    'minutes' => array_sum(array_map(fn (array $l) => $l['lesson']->getDurationMinutes(), $lessons))];
            }
        }

        return $out;
    }

    /** Ce qu'on fait dans une leçon : vidéo, questions, exercice (et où il va). */
    public static function lessonActivities(Lesson $lesson): array
    {
        $target = $lesson->hasPractice() ? $lesson->getTargetPlan() : null;

        return [
            'video' => $lesson->hasVideo(),
            'questions' => \count($lesson->getQuestions()),
            'target' => $target,
            'target_label' => null !== $target ? (PracticeCatalog::TARGETS[$target]['label'] ?? null) : null,
            'target_kind' => match (true) {
                null === $target => null,
                isset(PlanCatalog::PLANS[$target]) => 'plan',
                'COUPLE' === $target => 'couple',
                default => 'daily',
            },
        ];
    }

    /** Durée lisible : « 50 min », « 2 h 33 ». */
    public static function duration(int $minutes): string
    {
        return $minutes >= 60 ? intdiv($minutes, 60).' h '.str_pad((string) ($minutes % 60), 2, '0', \STR_PAD_LEFT) : $minutes.' min';
    }

    /** « Plan d'Hygiène de vie » → « Hygiène de vie » ; « Plan de Développement personnel » → « Développement ». */
    public static function short(string $label): string
    {
        $short = preg_replace('/^Plan (de |d\')/u', '', $label) ?? $label;

        return str_replace(' personnel', '', mb_strtoupper(mb_substr($short, 0, 1)).mb_substr($short, 1));
    }

    /** @return array<string, int> nombre d'exercices des leçons visibles par destination (clés de PracticeCatalog::TARGETS) */
    private static function targetCounts(Course $course): array
    {
        $counts = [];
        foreach ($course->getLessons(true) as $lesson) {
            if ($lesson->hasPractice() && null !== $lesson->getTargetPlan()) {
                $counts[$lesson->getTargetPlan()] = ($counts[$lesson->getTargetPlan()] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
