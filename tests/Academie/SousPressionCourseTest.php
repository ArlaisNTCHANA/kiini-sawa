<?php

namespace App\Tests\Academie;

use App\Academie\Command\ImportCourseCommand;
use App\Academie\Entity\Course;
use App\Academie\Entity\LessonDiagram;
use App\Academie\Service\AcademyImages;
use App\Academie\Service\LessonRenderer;
use App\Academie\Service\PracticeCatalog;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * Parcours vitrine « Sous pression » (27/11/2026) : les cinq pressions intérieures et la liberté de dire non. Le fichier
 * apporte ses schémas (créés dans l'atelier au chargement, jamais écrasés) ; un fichier de parcours peut aussi apporter
 * ses illustrations (fichiers à côté du YAML, enregistrées comme dans le studio).
 */
class SousPressionCourseTest extends KernelTestCase
{
    private const FILE = __DIR__.'/../../content/academie/sous-pression.yaml';

    private function em(): \Doctrine\ORM\EntityManagerInterface
    {
        return static::getContainer()->get('doctrine')->getManager();
    }

    public function testCourseIsCompleteAndFaithfulToTheHouseRules(): void
    {
        $data = Yaml::parseFile(self::FILE);
        $course = ImportCourseCommand::build($data);
        $diagrams = ImportCourseCommand::diagrams($data);

        $this->assertSame('CROISSANCE', $course->getPlace());
        $this->assertSame(['HYGIENE'], $course->getExtraPlans());
        $this->assertCount(8, $course->getLessons());
        $this->assertGreaterThanOrEqual(8, \count($diagrams));

        foreach ($course->getLessons() as $i => $lesson) {
            $text = $lesson->getContentMarkdown();
            $this->assertTrue($lesson->hasPractice() && PracticeCatalog::isValid($lesson->getTargetPlan(), $lesson->getTargetAction()), $lesson->getTitle());
            $this->assertCount(2, $lesson->getQuestions(), $lesson->getTitle());
            $this->assertStringContainsString(':::mediter', $text, 'Un temps « Méditer » par séance');
            // Chaque schéma appelé existe : dessiné dans le code, ou fourni par le fichier
            preg_match_all('/\[\[schema:([a-z0-9-]+)\]\]/', $text, $m);
            foreach ($m[1] as $code) {
                $this->assertTrue(isset(LessonRenderer::DIAGRAMS[$code]) || isset($diagrams[$code]), 'Schéma inconnu : '.$code);
            }
            // Interface en français : les noms anglais n'apparaissent qu'une fois, dans la première séance
            if ($i > 0) {
                $this->assertDoesNotMatchRegularExpression('/Be perfect|Please others|Hurry up|Be strong|Try hard/', $text, $lesson->getTitle());
            }
            $this->assertDoesNotMatchRegularExpression('/\bpartenaire\b(?! de Redevabilité)/iu', $text, 'Toujours « Partenaire de Redevabilité » en entier');
            $this->assertDoesNotMatchRegularExpression('/[a-zà-ÿ,]\n[a-zà-ÿ]/u', $text, 'Ligne coupée dans « '.$lesson->getTitle().' »');
        }
        $all = implode("\n", array_map(fn ($l) => $l->getContentMarkdown(), $course->getLessons()));
        $this->assertStringContainsString('Taibi Kahler', $all, 'La source du modèle est citée');
        $this->assertStringNotContainsString('Votre But de Dieu', $all);
    }

    public function testImportCreatesDiagramsOnceAndUploadsIllustrations(): void
    {
        $tester = new CommandTester((new Application(static::bootKernel()))->find('app:academie:import'));
        $dir = sys_get_temp_dir().'/ks-parcours-'.uniqid();
        mkdir($dir.'/illustrations', 0777, true);
        $img = imagecreatetruecolor(40, 20);
        imagepng($img, $dir.'/illustrations/porte.png');
        $slug = 'essai-illustre-'.uniqid();
        $code = 'essai-schema-'.uniqid();
        file_put_contents($dir.'/parcours.yaml', Yaml::dump([
            'slug' => $slug, 'title' => 'Essai illustré', 'access' => 'FREE',
            'diagrams' => [['code' => $code, 'title' => 'Deux colonnes', 'model' => 'compare', 'items' => [['title' => 'Avant', 'text' => 'Un'], ['title' => 'Après', 'text' => 'Deux']]]],
            'modules' => [['title' => 'Module', 'lessons' => [['title' => 'Leçon', 'content' => "Texte.\n\n![Une porte ouverte](illustrations/porte.png)\n\n[[schema:".$code."]]"]]]],
        ], 6));

        try {
            $this->assertSame(0, $tester->execute(['fichier' => $dir.'/parcours.yaml', '--verifier' => true]));
            $this->assertStringContainsString('1 schéma(s). 1 illustration(s).', preg_replace('/\s+/', ' ', $tester->getDisplay()));
            $this->assertNull($this->em()->getRepository(LessonDiagram::class)->findOneBy(['code' => $code]), '--verifier n\'enregistre rien');

            $this->assertSame(0, $tester->execute(['fichier' => $dir.'/parcours.yaml']));
            $diagram = $this->em()->getRepository(LessonDiagram::class)->findOneBy(['code' => $code]);
            $this->assertSame('compare', $diagram->getModel());
            $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => $slug]);
            $content = $course->getLessons()[0]->getContentMarkdown();
            $this->assertMatchesRegularExpression('#!\[Une porte ouverte\]\(/academie/images/'.$course->getId().'/[a-f0-9]{16}\.webp\)#', $content);
            $this->assertCount(1, static::getContainer()->get(AcademyImages::class)->list((int) $course->getId()));

            // Un schéma déjà présent (peut-être retouché dans l'atelier) n'est jamais écrasé
            $diagram->setTitle('Retouché dans l\'atelier');
            $this->em()->remove($course);
            $this->em()->flush();
            $this->assertSame(0, $tester->execute(['fichier' => $dir.'/parcours.yaml']));
            $this->em()->clear();
            $this->assertSame('Retouché dans l\'atelier', $this->em()->getRepository(LessonDiagram::class)->findOneBy(['code' => $code])->getTitle());

            // --textes : une illustration ajoutée au fichier est enregistrée, celle déjà en ligne est reprise sans
            // doublon, et une deuxième mise à jour n'est pas prise pour une retouche dans le studio (28/11/2026)
            $this->em()->clear();
            $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => $slug]);
            preg_match('#\(/academie/images/[^)]+\)#', $course->getLessons()[0]->getContentMarkdown(), $m);
            $first = $m[0];
            imagepng($img, $dir.'/illustrations/table.png');
            file_put_contents($dir.'/parcours.yaml', str_replace('[[schema:', '![Une table simple](illustrations/table.png)\n\n[[schema:', (string) file_get_contents($dir.'/parcours.yaml')));
            $this->assertSame(0, $tester->execute(['fichier' => $dir.'/parcours.yaml', '--textes' => true]), $tester->getDisplay());
            $this->assertStringContainsString('1 illustration(s) ajoutée(s)', preg_replace('/\s+/', ' ', $tester->getDisplay()));
            $this->em()->clear();
            $content = $this->em()->getRepository(Course::class)->findOneBy(['slug' => $slug])->getLessons()[0]->getContentMarkdown();
            $this->assertStringContainsString($first, $content, 'Image déjà en ligne reprise telle quelle');
            $this->assertMatchesRegularExpression('#!\[Une table simple\]\(/academie/images/\d+/[a-f0-9]{16}\.webp\)#', $content);
            $this->assertStringNotContainsString('illustrations/', $content, 'Plus aucun chemin de fichier dans le texte');
            $this->assertCount(2, static::getContainer()->get(AcademyImages::class)->list((int) $course->getId()));
            $this->assertSame(0, $tester->execute(['fichier' => $dir.'/parcours.yaml', '--textes' => true]), 'Deuxième passage accepté');
            $this->assertStringContainsString('0 leçon(s)', $tester->getDisplay());
            $this->assertCount(2, static::getContainer()->get(AcademyImages::class)->list((int) $course->getId()), 'Pas de doublon');

            // Une illustration absente ou une adresse internet est refusée
            file_put_contents($dir.'/mauvais.yaml', str_replace('illustrations/porte.png', 'https://exemple.org/x.png', (string) file_get_contents($dir.'/parcours.yaml')));
            $this->assertSame(1, $tester->execute(['fichier' => $dir.'/mauvais.yaml', '--verifier' => true]));
            $this->assertStringContainsString('Illustration', $tester->getDisplay());
        } finally {
            $course = $this->em()->getRepository(Course::class)->findOneBy(['slug' => $slug]);
            if ($course) {
                static::getContainer()->get(AcademyImages::class)->deleteAll((int) $course->getId());
                $this->em()->remove($course);
            }
            $diagram = $this->em()->getRepository(LessonDiagram::class)->findOneBy(['code' => $code]);
            if ($diagram) {
                $this->em()->remove($diagram);
            }
            $this->em()->flush();
            array_map('unlink', glob($dir.'/illustrations/*') ?: []);
            array_map('unlink', glob($dir.'/*.yaml') ?: []);
            @rmdir($dir.'/illustrations');
            @rmdir($dir);
        }
    }
}
