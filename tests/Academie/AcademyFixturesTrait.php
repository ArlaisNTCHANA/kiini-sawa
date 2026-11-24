<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseModule;
use App\Academie\Entity\Lesson;
use Doctrine\ORM\EntityManagerInterface;

/** Construit des parcours de test directement en base (le studio a ses propres tests). */
trait AcademyFixturesTrait
{
    /** @param list<array{0: string, 1: ?string, 2: ?string}> $lessons [titre, cible, action] */
    private function makeCourse(string $place = 'VIE', bool $free = true, bool $published = true, array $lessons = [['Première leçon', 'VIE', 'add_value']]): Course
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $slug = 'parcours-'.bin2hex(random_bytes(4));
        $course = (new Course())->setTitle('Parcours '.$slug)->setSlug($slug)->setSubtitle('Une accroche')->setPlace($place)
            ->setAccessLevel($free ? Course::ACCESS_FREE : Course::ACCESS_PRO);
        $module = (new CourseModule())->setTitle('Module 1');
        $course->addModule($module);
        foreach ($lessons as $i => [$title, $target, $action]) {
            $lesson = (new Lesson())->setTitle($title)->setSlug('lecon-'.($i + 1))->setContentMarkdown('Texte de la leçon '.($i + 1).'.')
                ->setDurationMinutes(5)->setVisible(true)->setPractice($target, $action)->setPracticePrompt($target ? 'Votre décision.' : null);
            $module->addLesson($lesson);
        }
        if ($published) {
            $course->publish();
        }
        $em->persist($course);
        $em->flush();

        return $course;
    }
}
