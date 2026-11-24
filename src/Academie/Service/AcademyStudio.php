<?php

namespace App\Academie\Service;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseModule;
use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonQuestion;
use Symfony\Component\String\Slugger\AsciiSlugger;

/** Règles du studio auteur : adresses, ordre (monter/descendre), conditions de publication et de suppression. */
final class AcademyStudio
{
    public static function slugify(string $text): string
    {
        return trim(mb_substr(mb_strtolower((string) (new AsciiSlugger('fr'))->slug($text)), 0, 80), '-');
    }

    /** Adresse d'une leçon, unique dans tout le parcours (adresse apprenant : /academie/{parcours}/{leçon}). */
    public static function lessonSlug(Course $course, string $title, ?Lesson $self = null): string
    {
        $base = self::slugify($title) ?: 'lecon';
        $taken = [];
        foreach ($course->getLessons() as $l) {
            if ($l !== $self) {
                $taken[] = $l->getSlug();
            }
        }
        $slug = $base;
        for ($i = 2; \in_array($slug, $taken, true); ++$i) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Monte (-1) ou descend (+1) un élément parmi ses voisins, puis renumérote 1, 2, 3…
     *
     * @param iterable<CourseModule|Lesson|LessonQuestion> $siblings dans l'ordre actuel
     */
    public static function move(iterable $siblings, object $item, int $direction): void
    {
        $list = [];
        foreach ($siblings as $s) {
            $list[] = $s;
        }
        $i = array_search($item, $list, true);
        $j = false === $i ? -1 : $i + ($direction < 0 ? -1 : 1);
        if ($j >= 0 && $j < \count($list)) {
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        }
        foreach ($list as $pos => $s) {
            $s->setSortOrder($pos + 1);
        }
    }

    /** Ce qui empêche de publier un parcours (liste vide : publiable). */
    public static function publishProblems(Course $course): array
    {
        $problems = [];
        $visible = $course->getLessons(true);
        if ([] === $visible) {
            $problems[] = 'Ajoutez au moins une leçon visible des apprenants.';
        }
        foreach ($visible as $lesson) {
            if ('' === trim($lesson->getContentMarkdown()) && !$lesson->hasVideo()) {
                $problems[] = 'La leçon « '.$lesson->getTitle().' » n\'a ni texte ni vidéo : complétez-la ou masquez-la.';
            }
        }

        return $problems;
    }

    /**
     * Supprimer est réservé aux parcours jamais publiés : une fois publié, des membres ont pu commencer, et leurs
     * progressions et notes ne doivent jamais disparaître. On masque une leçon, on repasse un parcours en brouillon.
     */
    public static function canDelete(Course $course): bool
    {
        return !$course->wasEverPublished();
    }
}
