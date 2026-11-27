<?php

namespace App\Tests\Academie;

use App\Academie\Command\ImportCourseCommand;
use App\Academie\Entity\Course;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * 27/11/2026 : « Mon Plan de Vie » était déjà publié, l'import refusait (à raison) de le remplacer. La commande
 * app:academie:remplacer-phrase corrige titres, présentation et consignes d'un parcours publié, sans toucher au texte
 * des leçons ni aux adresses des séances, et n'enregistre rien sans --enregistrer.
 */
class AcademyReplacePhraseTest extends KernelTestCase
{
    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    private function runPhrase(array $args): CommandTester
    {
        $tester = new CommandTester((new Application(static::bootKernel()))->find('app:academie:remplacer-phrase'));
        $tester->execute($args);

        return $tester;
    }

    public function testPublishedCourseWordingIsFixedWithoutTouchingLessonTextOrAddresses(): void
    {
        self::bootKernel();
        $data = Yaml::parseFile(__DIR__.'/../../content/academie/parcours-plan-de-vie.yaml');
        $slug = 'remplacer-phrase-'.uniqid();
        $data['slug'] = $slug;
        // L'état publié en production : l'ancienne formulation
        $data['description'] = str_replace('le But de Dieu pour ma vie', 'votre But de Dieu', $data['description']);
        $data['modules'][0]['lessons'][0]['title'] = 'Votre But de Dieu';
        $data['modules'][0]['lessons'][0]['practice']['prompt'] = str_replace('le But de Dieu pour ma vie', 'votre But de Dieu', $data['modules'][0]['lessons'][0]['practice']['prompt']);
        $course = ImportCourseCommand::build($data)->publish();
        $this->em()->persist($course);
        $this->em()->flush();
        $lesson = $course->getLessons()[0];
        $lessonSlug = $lesson->getSlug();
        $content = $lesson->getContentMarkdown();
        $id = $course->getId();
        $this->em()->clear();

        // Aperçu : rien n'est enregistré
        $tester = $this->runPhrase(['parcours' => $slug, 'ancien' => 'Votre But de Dieu', 'nouveau' => 'Le But de Dieu pour ma vie']);
        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('rien n\'est enregistré', $tester->getDisplay());
        $this->em()->clear();
        $this->assertSame('Votre But de Dieu', $this->em()->find(Course::class, $id)->getLessons()[0]->getTitle());

        $this->runPhrase(['parcours' => $slug, 'ancien' => 'Votre But de Dieu', 'nouveau' => 'Le But de Dieu pour ma vie', '--enregistrer' => true]);
        $this->runPhrase(['parcours' => $slug, 'ancien' => 'votre But de Dieu', 'nouveau' => 'le But de Dieu pour ma vie', '--enregistrer' => true]);

        $this->em()->clear();
        $course = $this->em()->find(Course::class, $id);
        $lesson = $course->getLessons()[0];
        $this->assertSame('Le But de Dieu pour ma vie', $lesson->getTitle());
        $this->assertStringContainsString('Vous voyez toujours où vous en êtes : le But de Dieu pour ma vie,', (string) $course->getDescription());
        $this->assertStringStartsWith('Écrivez le But de Dieu pour ma vie avec la formule de la méthode KIINI', (string) $lesson->getPracticePrompt());
        $this->assertSame($lessonSlug, $lesson->getSlug(), 'L\'adresse de la séance ne change pas');
        $this->assertSame($content, $lesson->getContentMarkdown(), 'Le texte de la leçon n\'est pas touché');
        $this->assertTrue($course->isPublished());

        $this->em()->remove($course);
        $this->em()->flush();
    }

    /** Règle « la Bible et le livre KIINI » (27/11/2026) : une liste de remplacements, tous les parcours, texte des leçons compris. */
    public function testListOfRewritesReachesLessonTextOnlyWithTheOption(): void
    {
        self::bootKernel();
        $data = Yaml::parseFile(__DIR__.'/../../content/academie/parcours-plan-de-vie.yaml');
        $slug = 'reformulations-'.uniqid();
        $data['slug'] = $slug;
        $data['modules'][0]['lessons'][0]['content'] = "Voici ce qu'en dit le livre KIINI, au chapitre 4.\n\nSi rien de clair ne vient, le livre le dit aussi : patience.";
        $course = ImportCourseCommand::build($data)->publish();
        $this->em()->persist($course);
        $this->em()->flush();
        $id = $course->getId();
        $this->em()->clear();
        $file = __DIR__.'/../../content/academie/reformulations-livre.yaml';

        $this->runPhrase(['parcours' => 'tous', '--fichier' => $file, '--enregistrer' => true]);
        $this->em()->clear();
        $this->assertStringContainsString("ce qu'en dit le livre KIINI", $this->em()->find(Course::class, $id)->getLessons()[0]->getContentMarkdown(), 'Sans --lecons, le texte des leçons ne change pas');

        $tester = $this->runPhrase(['parcours' => 'tous', '--fichier' => $file, '--lecons' => true, '--enregistrer' => true]);
        $this->assertSame(0, $tester->getStatusCode());
        $this->em()->clear();
        $text = $this->em()->find(Course::class, $id)->getLessons()[0]->getContentMarkdown();
        $this->assertStringContainsString('Voici ce que propose le livre KIINI, au chapitre 4.', $text);
        $this->assertStringContainsString('le livre KIINI le rappelle', $text);

        $this->em()->remove($this->em()->find(Course::class, $id));
        $this->em()->flush();
    }

    public function testUnknownCourseIsRefused(): void
    {
        $this->assertSame(1, $this->runPhrase(['parcours' => 'inconnu-'.uniqid(), 'ancien' => 'a', 'nouveau' => 'b'])->getStatusCode());
    }
}
