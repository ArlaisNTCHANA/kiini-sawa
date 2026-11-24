<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Academie\Entity\LessonDiagram;
use App\Academie\Repository\LessonDiagramRepository;
use App\Academie\Service\DiagramModels;
use App\Academie\Service\LessonRenderer;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Atelier de schémas du studio : création sans code à partir d'un modèle, aperçu en direct, contenu vérifié et échappé,
 * schéma placé dans une leçon par son code, jamais supprimé tant qu'une leçon l'utilise.
 */
class AcademyDiagramWorkshopTest extends WebTestCase
{
    use AcademyFixturesTrait;

    private function admin(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    private function token(KernelBrowser $client): string
    {
        return (string) $client->request('GET', '/admin/academie/schemas/nouveau/cards')->filter('input[name="_csrf_token"]')->attr('value');
    }

    private function save(KernelBrowser $client, string $url, string $title, string $model, array $data): void
    {
        $client->request('POST', $url, ['_csrf_token' => $this->token($client), 'payload' => json_encode(['title' => $title, 'model' => $model, 'data' => $data])]);
    }

    private function removeAll(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->createQuery('DELETE FROM '.LessonDiagram::class.' d WHERE d.title LIKE :t')->setParameter('t', 'Test atelier%')->execute();
    }

    public function testModelsValidateAndCleanTheContent(): void
    {
        $data = DiagramModels::normalize('cards', ['items' => [
            ['title' => '<b>Une</b> carte', 'text' => 'Texte', 'icon' => 'sprout', 'tone' => 'vivant'],
            ['title' => 'Deux', 'icon' => 'inconnue', 'tone' => 'fuchsia'],
            ['title' => '', 'text' => ''],
        ]]);
        $this->assertCount(2, $data['items'], 'Les éléments vides sont ignorés');
        $this->assertSame('Une carte', $data['items'][0]['title'], 'Aucune balise');
        $this->assertSame('', $data['items'][1]['icon'], 'Icône hors liste refusée');
        $this->assertSame('neutre', $data['items'][1]['tone'], 'Teinte hors liste refusée');

        foreach ([['cards', [['title' => 'Seule']]], ['compare', [['title' => 'A'], ['title' => 'B'], ['title' => 'C']]], ['steps', [['text' => 'sans titre'], ['title' => 'B']]], ['inconnu', []]] as [$model, $items]) {
            try {
                DiagramModels::normalize($model, ['items' => $items]);
                $this->fail('Accepté à tort : '.$model);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        foreach (array_keys(DiagramModels::MODELS) as $model) {
            $this->assertNotEmpty(DiagramModels::normalize($model, DiagramModels::starter($model))['items'], 'Exemple de départ valable : '.$model);
        }
    }

    public function testCreateEditPlaceAndProtectADiagram(): void
    {
        $client = $this->admin();
        $this->removeAll();
        try {
            // Page de l'atelier et éditeur
            $client->request('GET', '/admin/academie/schemas');
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('.ac-models', 'Comparaison');
            $client->request('GET', '/admin/academie/schemas/nouveau/compare');
            $this->assertResponseIsSuccessful();

            // Aperçu en direct, sans rien enregistrer ; le texte est échappé
            $client->request('POST', '/admin/academie/schemas/apercu', ['_csrf_token' => $this->token($client), 'payload' => json_encode(['title' => 'Test atelier', 'model' => 'compare', 'data' => DiagramModels::starter('compare')])]);
            $this->assertStringContainsString('academie-schema--perso', (string) $client->getResponse()->getContent());
            $this->assertNull(static::getContainer()->get(LessonDiagramRepository::class)->findOneBy(['title' => 'Test atelier']));

            // Création : un modèle incomplet est refusé, puis accepté une fois rempli
            $this->save($client, '/admin/academie/schemas/nouveau/compare', 'Test atelier <script>', 'compare', ['items' => [['title' => 'Seule colonne']]]);
            $this->assertSelectorTextContains('.ks-alert--danger', 'exactement 2');
            $this->save($client, '/admin/academie/schemas/nouveau/compare', 'Test atelier : l\'usine et la forêt', 'compare', ['note' => 'Une note', 'items' => [
                ['title' => 'Usine <img src=x onerror=alert(1)>', 'text' => "Vitesse\nForce", 'icon' => 'factory', 'tone' => 'froid'],
                ['title' => 'Forêt', 'text' => "Rythme\nNature", 'icon' => 'trees', 'tone' => 'vivant'],
            ]]);
            $this->assertResponseRedirects();
            $diagram = static::getContainer()->get(LessonDiagramRepository::class)->findOneBy(['title' => 'Test atelier : l\'usine et la forêt']);
            $this->assertNotNull($diagram);
            $this->assertStringStartsWith('test-atelier', $diagram->getCode());
            $this->assertArrayNotHasKey($diagram->getCode(), LessonRenderer::DIAGRAMS);

            // Placé dans une leçon : rendu comme les autres schémas, sans faille
            $course = $this->makeCourse(published: false);
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $lesson = $em->find(Course::class, $course->getId())->getLessons()[0];
            $lesson->setContentMarkdown("Avant\n\n".$diagram->token());
            $em->flush();
            $html = static::getContainer()->get(LessonRenderer::class)->toHtml($lesson->getContentMarkdown());
            $this->assertStringContainsString('Rythme', $html);
            $this->assertStringNotContainsString('<img src=x', $html);

            // Proposé dans l'éditeur de leçon
            $client->request('GET', '/admin/academie/lecon/'.$lesson->getId());
            $this->assertSelectorTextContains('.ac-insert', 'Test atelier : l\'usine et la forêt');

            // Modifier le titre ne change pas le code (les leçons restent justes)
            $code = $diagram->getCode();
            $this->save($client, '/admin/academie/schemas/'.$diagram->getId(), 'Test atelier renommé', 'compare', $diagram->getData());
            $em->clear();
            $this->assertSame($code, static::getContainer()->get(LessonDiagramRepository::class)->find($diagram->getId())->getCode());

            // Suppression refusée tant qu'une leçon l'utilise
            $client->request('POST', '/admin/academie/schemas/'.$diagram->getId().'/supprimer', ['_csrf_token' => $this->token($client)]);
            $this->assertNotNull(static::getContainer()->get(LessonDiagramRepository::class)->find($diagram->getId()));
            $lesson = $em->find(\App\Academie\Entity\Lesson::class, $lesson->getId());
            $lesson->setContentMarkdown('Sans schéma');
            $em->flush();
            $client->request('POST', '/admin/academie/schemas/'.$diagram->getId().'/supprimer', ['_csrf_token' => $this->token($client)]);
            $em->clear();
            $this->assertNull(static::getContainer()->get(LessonDiagramRepository::class)->find($diagram->getId()));

            // Sans jeton : refusé
            $client->request('POST', '/admin/academie/schemas/apercu', ['payload' => '{}']);
            $this->assertResponseStatusCodeSame(403);
        } finally {
            $this->removeAll();
        }
    }

    public function testOnlyAdministratorsOpenTheWorkshop(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/academie/schemas');
        $this->assertResponseRedirects();
    }
}
