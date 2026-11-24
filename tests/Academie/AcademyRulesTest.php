<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseModule;
use App\Academie\Entity\Lesson;
use App\Academie\Service\AcademyPlaces;
use App\Academie\Service\AcademyStudio;
use App\Academie\Service\PracticeCatalog;
use App\Academie\Service\VideoEmbed;
use App\PlanDeVie\Service\PlanCatalog;
use PHPUnit\Framework\TestCase;

/** Règles de l'Académie sans base de données : vidéos acceptées, exercices possibles, ordre, rattachement aux plans. */
class AcademyRulesTest extends TestCase
{
    public function testYoutubeAndVimeoLinksAreRecognised(): void
    {
        $yt = [Lesson::MEDIA_YOUTUBE, 'dQw4w9WgXcQ'];
        foreach ([
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'youtube.com/watch?v=dQw4w9WgXcQ&t=42s', 'https://youtu.be/dQw4w9WgXcQ?si=x',
            'https://m.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ',
        ] as $url) {
            $this->assertSame($yt, VideoEmbed::parse($url), $url);
        }
        $this->assertSame([Lesson::MEDIA_VIMEO, '76979871'], VideoEmbed::parse('https://vimeo.com/76979871'));
        $this->assertSame([Lesson::MEDIA_VIMEO, '76979871'], VideoEmbed::parse('https://player.vimeo.com/video/76979871'));
        $this->assertSame([Lesson::MEDIA_VIMEO, '76979871/abc123def4'], VideoEmbed::parse('https://vimeo.com/76979871/abc123def4'));
    }

    public function testOtherLinksAreRefused(): void
    {
        foreach ([
            'https://youtube.com.exemple.org/watch?v=dQw4w9WgXcQ', 'https://exemple.org/?u=youtube.com/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=court', 'javascript:alert(1)', 'https://vimeo.com/channels/abc', 'https://dailymotion.com/video/x7', '',
        ] as $url) {
            $this->assertNull(VideoEmbed::parse($url), $url);
        }
    }

    public function testEmbedAddressesAreRebuiltFromTheIdentifierOnly(): void
    {
        $this->assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&autoplay=1', VideoEmbed::embedUrl(Lesson::MEDIA_YOUTUBE, 'dQw4w9WgXcQ'));
        $this->assertSame('https://player.vimeo.com/video/76979871?dnt=1&autoplay=1&h=abc123def4', VideoEmbed::embedUrl(Lesson::MEDIA_VIMEO, '76979871/abc123def4'));
        $this->assertNull(VideoEmbed::embedUrl(Lesson::MEDIA_YOUTUBE, '"><script>'));
        $this->assertNull(VideoEmbed::embedUrl(Lesson::MEDIA_NONE, 'dQw4w9WgXcQ'));
    }

    public function testExercisesCoverTheSixPlansAndExistingEntities(): void
    {
        foreach (array_keys(PlanCatalog::PLANS) as $plan) {
            $this->assertArrayHasKey($plan, PracticeCatalog::TARGETS, 'Chacun des 6 plans a au moins un exercice.');
        }
        $modules = ['Value' => 'PlanDeVie', 'LifeGoal' => 'PlanDeVie', 'GrowthGoal' => 'PlanDeCroissance', 'Principle' => 'PlanDeCroissance',
            'SkillGoal' => 'PlanDeDeveloppement', 'LearningResource' => 'PlanDeDeveloppement', 'HygieneDomain' => 'PlanDHygiene',
            'StewardshipItem' => 'PlanDIntendance', 'LegacyGoal' => 'PlanDeTransmission', 'TransmissionAction' => 'PlanDeTransmission',
            'Habit' => 'Rituels', 'ReflectionNote' => 'Journal', 'CoupleItem' => 'Couple'];
        foreach (PracticeCatalog::TARGETS as $target) {
            foreach ($target['actions'] as $action => $def) {
                $this->assertArrayHasKey($def['entity'], $modules, $action);
                $this->assertTrue(class_exists('App\\'.$modules[$def['entity']].'\\Entity\\'.$def['entity']), $action);
                $this->assertStringNotContainsString('update', $action, 'L\'Académie ajoute, elle ne remplace jamais.');
            }
        }
        $this->assertSame('Plan d\'Intendance : Ajouter un élément à gérer', PracticeCatalog::label('INTENDANCE', 'add_stewardship_item'));
        $this->assertNull(PracticeCatalog::label('INTENDANCE', 'add_value'));
        $this->assertSame('Enregistrer dans mon Plan d\'Intendance', PracticeCatalog::button('INTENDANCE'));
    }

    public function testCoupleIsKeptApartFromTheSixPlans(): void
    {
        $this->assertSame(array_keys(PlanCatalog::PLANS), array_keys(AcademyPlaces::plans()));
        $this->assertArrayNotHasKey('COUPLE', AcademyPlaces::plans());
        $this->assertSame('Sanctuaire du Couple', AcademyPlaces::label('COUPLE'));
        $course = (new Course())->setPlace('COUPLE');
        $this->assertTrue($course->isCouple());
        $this->assertNull($course->getPrimaryPlan());
        $course->setPlace('VIE');
        $this->assertFalse($course->isCouple());
        $this->assertFalse(AcademyPlaces::isValid('SEPTIEME'));
    }

    public function testMoveSwapsNeighboursAndRenumbers(): void
    {
        $module = new CourseModule();
        foreach (['a', 'b', 'c'] as $t) {
            $module->addLesson((new Lesson())->setTitle($t));
        }
        $lessons = $module->getLessons()->toArray();
        AcademyStudio::move($module->getLessons(), $lessons[0], -1); // déjà en tête : rien ne bouge
        AcademyStudio::move($module->getLessons(), $lessons[0], 1);
        $this->assertSame([2, 1, 3], array_map(fn (Lesson $l) => $l->getSortOrder(), $lessons));
    }

    public function testLessonAddressesAreUniqueInTheWholeCourse(): void
    {
        $course = new Course();
        $course->addModule($m1 = new CourseModule());
        $course->addModule($m2 = new CourseModule());
        $m1->addLesson((new Lesson())->setSlug('introduction'));
        $this->assertSame('introduction-2', AcademyStudio::lessonSlug($course, 'Introduction'));
        $this->assertSame('etre-intendant-de-son-temps', AcademyStudio::lessonSlug($course, 'Être intendant de son temps !'));
    }
}
