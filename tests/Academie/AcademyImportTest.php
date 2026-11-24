<?php

namespace App\Tests\Academie;

use App\Academie\Command\ImportCourseCommand;
use App\Academie\Entity\Course;
use App\Academie\Service\PracticeCatalog;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * Chargement d'un parcours écrit dans un fichier (app:academie:import) : toujours en brouillon, jamais par-dessus un
 * parcours publié, et le parcours « De la productivité à la fécondité » livré dans content/academie/ est valable.
 */
class AcademyImportTest extends KernelTestCase
{
    private const FILE = __DIR__.'/../../content/academie/de-la-productivite-a-la-fecondite.yaml';

    private function tester(): CommandTester
    {
        return new CommandTester((new Application(static::bootKernel()))->find('app:academie:import'));
    }

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    private function removeCourse(string $slug): void
    {
        $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => $slug]);
        if ($course) {
            $this->em()->remove($course);
            $this->em()->flush();
        }
    }

    public function testDeliveredCourseIsValidAndFaithfulToTheStudioRules(): void
    {
        $course = ImportCourseCommand::build(Yaml::parseFile(self::FILE));

        $this->assertSame('de-la-productivite-a-la-fecondite', $course->getSlug());
        $this->assertSame('HYGIENE', $course->getPlace());
        $this->assertFalse($course->isFree());
        $this->assertCount(5, $course->getModules());
        $this->assertCount(22, $course->getLessons());
        $slugs = array_map(fn ($l) => $l->getSlug(), $course->getLessons());
        $this->assertSame($slugs, array_unique($slugs), 'Adresses de leçons uniques');
        foreach ($course->getLessons() as $lesson) {
            $this->assertNotSame('', trim($lesson->getContentMarkdown()));
            $this->assertLessThanOrEqual(160, mb_strlen($lesson->getTitle()));
            if ($lesson->hasPractice()) {
                $this->assertTrue(PracticeCatalog::isValid($lesson->getTargetPlan(), $lesson->getTargetAction()));
            }
            // Le site garde les retours à la ligne : aucune phrase coupée au milieu d'un paragraphe
            $this->assertDoesNotMatchRegularExpression('/[a-zà-ÿ,]\n[a-zà-ÿ]/u', $lesson->getContentMarkdown(), 'Ligne coupée dans « '.$lesson->getTitle().' »');
        }
        $text = implode("\n", array_map(fn ($l) => $l->getContentMarkdown(), $course->getLessons()));
        $this->assertStringNotContainsString('Marc', $text, 'Personnage jamais présenté dans la présentation : retiré');
        $this->assertStringContainsString('Partenaire de Redevabilité', $text);
    }

    public function testImportCreatesADraftAndNeverOverwritesAPublishedCourse(): void
    {
        $tester = $this->tester();
        $this->removeCourse('de-la-productivite-a-la-fecondite');
        try {
            $this->assertSame(0, $tester->execute(['fichier' => self::FILE, '--verifier' => true]));
            $this->assertNull($this->em()->getRepository(Course::class)->findOneBy(['slug' => 'de-la-productivite-a-la-fecondite']), '--verifier n\'enregistre rien');

            $this->assertSame(0, $tester->execute(['fichier' => self::FILE]));
            $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => 'de-la-productivite-a-la-fecondite']);
            $this->assertNotNull($course);
            $this->assertFalse($course->isPublished(), 'Toujours en brouillon');
            $this->assertCount(22, $course->getLessons());

            $this->assertSame(1, $tester->execute(['fichier' => self::FILE]), 'Pas de doublon sans --remplacer');
            $this->assertSame(0, $tester->execute(['fichier' => self::FILE, '--remplacer' => true]), 'Un brouillon jamais publié peut être remplacé');

            // Brouillon retouché dans le studio après son chargement : jamais remplacé (le travail serait perdu)
            $this->em()->createQuery('UPDATE '.Course::class.' c SET c.createdAt = :d WHERE c.slug = :s')
                ->setParameters(['d' => new \DateTimeImmutable('-1 hour'), 's' => 'de-la-productivite-a-la-fecondite'])->execute();
            $this->em()->clear();
            $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => 'de-la-productivite-a-la-fecondite']);
            $course->getLessons()[0]->setTitle('Titre retouché dans le studio');
            $this->em()->flush();
            $this->assertSame(1, $tester->execute(['fichier' => self::FILE, '--remplacer' => true]));
            $this->assertStringContainsString('modifié dans le studio', $tester->getDisplay());

            // --textes : met à jour le texte des leçons d'un parcours (même publié) sans le recréer
            $this->removeCourse('de-la-productivite-a-la-fecondite');
            $this->assertSame(0, $tester->execute(['fichier' => self::FILE]));
            $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => 'de-la-productivite-a-la-fecondite']);
            $course->publish();
            $first = $course->getLessons()[0];
            $firstId = $first->getId();
            $this->em()->getConnection()->executeStatement('UPDATE academy_lesson SET content_markdown = ? WHERE id = ?', ['Ancien texte', $firstId]);
            $this->em()->flush();
            $this->em()->clear();
            $this->assertSame(0, $tester->execute(['fichier' => self::FILE, '--textes' => true]));
            $this->em()->clear();
            $updated = $this->em()->find(\App\Academie\Entity\Lesson::class, $firstId);
            $this->assertNotSame('Ancien texte', $updated->getContentMarkdown(), 'Même leçon (même identifiant), texte mis à jour');
            $this->assertTrue($updated->getCourse()->isPublished());

            $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => 'de-la-productivite-a-la-fecondite']);
            $course->publish();
            $this->em()->flush();
            $this->assertSame(1, $tester->execute(['fichier' => self::FILE, '--remplacer' => true]), 'Un parcours publié n\'est jamais remplacé');
            $this->assertStringContainsString('jamais remplacé', $tester->getDisplay());
        } finally {
            $this->em()->clear();
            $this->removeCourse('de-la-productivite-a-la-fecondite');
        }
    }

    public function testInvalidFilesAreRefused(): void
    {
        $base = ['title' => 'Essai', 'place' => 'VIE', 'modules' => [['title' => 'M', 'lessons' => [['title' => 'L', 'content' => 'Texte']]]]];
        $this->assertCount(1, ImportCourseCommand::build($base)->getLessons());

        foreach ([
            'rattachement' => ['place' => 'INCONNU'] + $base,
            'exercice' => ['modules' => [['lessons' => [['title' => 'L', 'content' => 'T', 'practice' => ['target' => 'VIE', 'action' => 'effacer_tout']]]]]] + $base,
            'question' => ['modules' => [['lessons' => [['title' => 'L', 'content' => 'T', 'questions' => [['question' => 'Q', 'answers' => ['a', 'b'], 'correct' => [5]]]]]]]] + $base,
            'vide' => ['modules' => []] + $base,
        ] as $case => $data) {
            try {
                ImportCourseCommand::build($data);
                $this->fail('Fichier accepté à tort : '.$case);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
