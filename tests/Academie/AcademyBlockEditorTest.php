<?php

namespace App\Tests\Academie;

use App\Academie\Command\ImportCourseCommand;
use App\Academie\Entity\Lesson;
use App\Academie\Service\LessonBlocks;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Éditeur de leçon par blocs : le texte passe en blocs et revient sans rien perdre, les blocs envoyés sont nettoyés,
 * et une leçon enregistrée depuis les blocs garde le même format de texte qu'avant.
 */
class AcademyBlockEditorTest extends WebTestCase
{
    use AcademyFixturesTrait;

    private function admin(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    public function testTextToBlocksAndBackLosesNothing(): void
    {
        $course = ImportCourseCommand::build(Yaml::parseFile(__DIR__.'/../../content/academie/de-la-productivite-a-la-fecondite.yaml'));
        foreach ($course->getLessons() as $lesson) {
            $md = trim($lesson->getContentMarkdown());
            $this->assertSame($md, LessonBlocks::toMarkdown(LessonBlocks::sanitize(LessonBlocks::fromMarkdown($md))), 'Leçon « '.$lesson->getTitle().' »');
        }

        $blocks = LessonBlocks::fromMarkdown("## Un titre\n\nUn paragraphe\nsur deux lignes.\n\n- un\n- deux\n\n1. premier\n2. second\n\n> « Citation »\n> Source\n\n:::retenir Le principe\nTexte\n:::\n\n[[schema:cinq-principes]]\n\n![Forêt](/academie/images/3/0123456789abcdef.webp)\n\n| a | b |\n| --- | --- |\n| 1 | 2 |");
        $this->assertSame(['titre', 'paragraphe', 'liste', 'liste', 'citation', 'encadre', 'schema', 'image', 'avance'], array_column($blocks, 'type'));
        $this->assertTrue($blocks[3]['ordered']);
        $this->assertSame("un\ndeux", $blocks[2]['items']);
        $this->assertSame('Le principe', $blocks[5]['title']);
    }

    public function testSentBlocksAreCleaned(): void
    {
        $clean = LessonBlocks::sanitize([
            ['type' => 'paragraphe', 'text' => 'Gardé'],
            ['type' => 'paragraphe', 'text' => '   '],
            ['type' => 'script', 'text' => 'non'],
            ['type' => 'image', 'src' => 'https://ailleurs.com/x.jpg', 'alt' => 'non'],
            ['type' => 'image', 'src' => '/academie/images/3/0123456789abcdef.webp', 'alt' => 'Une ] forêt'],
            ['type' => 'schema', 'code' => '../../etc'],
            ['type' => 'encadre', 'kind' => 'inconnu', 'text' => 'non'],
            ['type' => 'encadre', 'kind' => 'avous', 'title' => 'À vous', 'text' => "Texte\n:::\nfin"],
            ['type' => 'titre', 'level' => 7, 'text' => 'Titre'],
            'pas un bloc',
        ]);
        $this->assertSame(['paragraphe', 'image', 'encadre', 'titre'], array_column($clean, 'type'));
        $this->assertSame('Une  forêt', $clean[1]['alt'], 'Pas de crochet qui casserait l\'image');
        $this->assertStringNotContainsString(':::', $clean[2]['text'], 'Un encadré ne peut pas en fermer un autre');
        $this->assertSame(2, $clean[3]['level']);
    }

    public function testLessonSavedFromBlocksKeepsTheTextFormat(): void
    {
        $client = $this->admin();
        $course = $this->makeCourse(published: false);
        $lesson = $course->getLessons()[0];
        $crawler = $client->request('GET', '/admin/academie/lecon/'.$lesson->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[x-data^="lessonEditor"]');
        $token = (string) $crawler->filter('form input[name="_csrf_token"]')->first()->attr('value');

        // Conversion pour passer d'un mode à l'autre
        $client->request('POST', '/admin/academie/blocs', ['_csrf_token' => $token, 'content' => "## Titre\n\nTexte"]);
        $this->assertSame(['titre', 'paragraphe'], array_column(json_decode($client->getResponse()->getContent(), true)['blocks'], 'type'));
        $client->request('POST', '/admin/academie/blocs', ['_csrf_token' => $token, 'blocks' => json_encode([['type' => 'liste', 'ordered' => true, 'items' => "un\ndeux"]])]);
        $this->assertSame("1. un\n2. deux", json_decode($client->getResponse()->getContent(), true)['content']);

        // Aperçu depuis les blocs
        $client->request('POST', '/admin/academie/apercu-texte', ['_csrf_token' => $token, 'blocks' => json_encode([['type' => 'encadre', 'kind' => 'retenir', 'title' => '', 'text' => 'Important']])]);
        $this->assertStringContainsString('academie-encadre--retenir', (string) $client->getResponse()->getContent());

        // Enregistrement depuis les blocs
        $client->request('GET', '/admin/academie/lecon/'.$lesson->getId());
        $client->submitForm('Enregistrer la leçon', [
            'editor' => 'blocs',
            'blocks' => json_encode([
                ['type' => 'titre', 'level' => 2, 'text' => 'Partie une'],
                ['type' => 'paragraphe', 'text' => 'Un **mot**.'],
                ['type' => 'schema', 'code' => 'cinq-principes'],
            ]),
        ]);
        $this->assertResponseRedirects();
        static::getContainer()->get(EntityManagerInterface::class)->clear();
        $saved = static::getContainer()->get(EntityManagerInterface::class)->find(Lesson::class, $lesson->getId());
        $this->assertSame("## Partie une\n\nUn **mot**.\n\n[[schema:cinq-principes]]", $saved->getContentMarkdown());

        // Sans jeton : refusé
        $client->request('POST', '/admin/academie/blocs', ['content' => 'x']);
        $this->assertResponseStatusCodeSame(403);
    }
}
