<?php

namespace App\Tests\Academie;

use App\Academie\Command\ImportCourseCommand;
use App\Academie\Entity\Course;
use App\Academie\Entity\Lesson;
use App\Academie\Service\CourseExporter;
use App\Compte\Repository\UserRepository;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Export d'un parcours depuis le studio (27/11/2026, demande du fondateur) : PDF, Word et fichier de parcours.
 * Le fichier de parcours se recharge sans rien perdre (vidéo et leçon masquée comprises) ; seuls les administrateurs
 * peuvent exporter.
 */
class CourseExportTest extends WebTestCase
{
    use AccountTestTrait;

    private function course(): Course
    {
        $data = Yaml::parseFile(__DIR__.'/../../content/academie/choix-et-engagement.yaml');
        $data['slug'] = 'export-'.uniqid();
        $course = ImportCourseCommand::build($data);
        $lessons = $course->getLessons();
        $lessons[0]->setVideo(Lesson::MEDIA_YOUTUBE, 'dQw4w9WgXcQ');
        $lessons[1]->setVisible(false);
        $this->em()->persist($course);
        $this->em()->flush();

        return $course;
    }

    public function testCourseFileReloadsWithoutLosingAnything(): void
    {
        static::createClient();
        $course = $this->course();
        $yaml = static::getContainer()->get(CourseExporter::class)->yaml($course);
        $this->assertStringStartsWith('# Parcours « Choix et engagement »', $yaml);

        $again = ImportCourseCommand::build(Yaml::parse($yaml));
        $this->assertSame($course->getSlug(), $again->getSlug());
        $this->assertSame([$course->getTitle(), $course->getSubtitle(), trim((string) $course->getDescription()), $course->getPlace(), $course->getAccessLevel(), $course->getKind()],
            [$again->getTitle(), $again->getSubtitle(), trim((string) $again->getDescription()), $again->getPlace(), $again->getAccessLevel(), $again->getKind()]);
        $fingerprint = fn (Course $c) => array_map(fn (Lesson $l) => [
            $l->getTitle(), trim($l->getContentMarkdown()), $l->getDurationMinutes(), $l->isVisible(), $l->getMediaProvider(), $l->getMediaRef(),
            $l->getTargetPlan(), $l->getTargetAction(), $l->getPracticePrompt(),
            array_map(fn ($q) => [$q->getQuestion(), $q->getOptions(), $q->getCorrectOptionIds(), $q->getExplanation()], $l->getQuestions()->toArray()),
        ], $c->getLessons());
        $this->assertSame($fingerprint($course), $fingerprint($again));
        $this->assertFalse($again->getLessons()[1]->isVisible());
        $this->assertTrue($again->getLessons()[0]->hasVideo());

        $this->em()->remove($course);
        $this->em()->flush();
    }

    public function testAdminDownloadsPdfWordAndCourseFile(): void
    {
        $client = static::createClient();
        $course = $this->course();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        $client->request('GET', '/admin/academie/'.$course->getId().'/exporter/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $client->getResponse()->getContent());
        $this->assertStringContainsString('parcours-'.$course->getSlug().'.pdf', (string) $client->getResponse()->headers->get('Content-Disposition'));

        $client->request('GET', '/admin/academie/'.$course->getId().'/exporter/docx');
        $this->assertResponseIsSuccessful();
        $file = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($file, (string) $client->getResponse()->getContent());
        $zip = new \ZipArchive();
        $this->assertTrue(true === $zip->open($file));
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        unlink($file);
        $this->assertStringContainsString('Choix et engagement', $xml);
        $this->assertStringContainsString('La vie commence sans avoir le choix', $xml);
        $this->assertStringContainsString('(bonne réponse)', $xml);
        $this->assertStringContainsString('leçon masquée aux membres', $xml);
        $this->assertStringNotContainsString('**', $xml, 'Aucune marque de mise en forme dans le document Word');

        $client->request('GET', '/admin/academie/'.$course->getId().'/exporter/yaml');
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('video: \'https://www.youtube.com/watch?v=dQw4w9WgXcQ\'', (string) $client->getResponse()->getContent());

        $crawler = $client->request('GET', '/admin/academie/'.$course->getId());
        $this->assertCount(3, $crawler->filter('.ac-export__menu a'));

        $this->em()->remove($this->em()->find(Course::class, $course->getId()));
        $this->em()->flush();
    }

    public function testMembersCannotExport(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/academie/1/exporter/yaml');
        $this->assertResponseStatusCodeSame(403);
    }
}
