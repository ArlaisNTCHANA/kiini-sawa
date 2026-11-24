<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Academie\Service\AcademyImages;
use App\Academie\Service\LessonMarkdown;
use App\Academie\Service\LessonRenderer;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Leçons plus visuelles : encadrés, schémas dessinés, images envoyées dans le studio (réenregistrées, servies sans
 * connexion, seules images acceptées dans le texte, jamais supprimées tant qu'une leçon les utilise).
 */
class AcademyVisualTest extends WebTestCase
{
    use AcademyFixturesTrait;

    private function admin(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    private function token(KernelBrowser $client, int $courseId): string
    {
        $crawler = $client->request('GET', '/admin/academie/'.$courseId);

        return (string) $crawler->filter('#images input[name="_csrf_token"]')->attr('value');
    }

    private function png(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'img').'.png';
        $im = imagecreatetruecolor(2400, 1200);
        imagefill($im, 0, 0, imagecolorallocate($im, 46, 107, 83));
        imagepng($im, $path);

        return new UploadedFile($path, 'illustration.png', 'image/png', null, true);
    }

    public function testCalloutsAndDiagramsAreRenderedSafely(): void
    {
        static::createClient();
        $renderer = static::getContainer()->get(LessonRenderer::class);
        $html = $renderer->toHtml(":::retenir Le principe\nUn **mot** important.\n:::\n\n[[schema:cinq-principes]]\n\n:::inconnu\nTexte\n:::\n\n[[schema:nexiste-pas]]\n\n<script>alert(1)</script>");

        $this->assertStringContainsString('academie-encadre--retenir', $html);
        $this->assertStringContainsString('Le principe', $html);
        $this->assertStringContainsString('<strong>mot</strong>', $html);
        $this->assertStringContainsString('academie-schema--cinq-principes', $html);
        $this->assertStringContainsString('L\'Élagage', $html);
        $this->assertStringContainsString(':::inconnu', $html, 'Un encadré inconnu reste du texte');
        $this->assertStringNotContainsString('nexiste-pas', $html, 'Un schéma inconnu disparaît');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('KSBLOC', $html);

        foreach (array_keys(LessonRenderer::DIAGRAMS) as $code) {
            $this->assertNotNull($renderer->diagram($code), 'Schéma '.$code);
        }
        // Encadré jamais fermé : le texte reste visible
        $this->assertStringContainsString('Texte perdu ?', LessonMarkdown::toHtml(":::exemple\nTexte perdu ?"));
    }

    public function testOnlyStudioImagesAreShown(): void
    {
        $own = '/academie/images/12/0123456789abcdef.webp';
        $html = LessonMarkdown::toHtml("![Une forêt]($own)\n\n![Ailleurs](https://exemple.com/photo.jpg)\n\n![Piège](/academie/images/12/../../../.env)");

        $this->assertStringContainsString('<img src="'.$own.'" alt="Une forêt" loading="lazy" class="academie-image">', $html);
        $this->assertStringNotContainsString('exemple.com', $html);
        $this->assertStringNotContainsString('.env', $html);
        $this->assertStringContainsString('src="https://kiinisawa.com'.$own.'"', LessonMarkdown::toHtml("![x]($own)", null, 'https://kiinisawa.com'), 'Adresse absolue pour l\'application mobile');
    }

    public function testStudioUploadServeAndDeleteImages(): void
    {
        $client = $this->admin();
        $course = $this->makeCourse(published: false);
        $id = (int) $course->getId();
        $images = static::getContainer()->get(AcademyImages::class);
        try {
            $client->request('POST', '/admin/academie/'.$id.'/images', ['_csrf_token' => $this->token($client, $id)], ['image' => $this->png()]);
            $this->assertResponseRedirects();
            $list = $images->list($id);
            $this->assertCount(1, $list);
            $path = $list[0]['path'];
            $this->assertTrue(AcademyImages::isPublicPath($path));
            $size = getimagesize($images->file($id, $list[0]['name']));
            $this->assertSame(AcademyImages::MAX_WIDTH, $size[0], 'Réduite à 1600 pixels de large');
            $this->assertSame('image/webp', $size['mime']);

            // Une « image » qui n'en est pas une est refusée
            $fake = tempnam(sys_get_temp_dir(), 'txt');
            file_put_contents($fake, '<?php echo "non";');
            $client->request('POST', '/admin/academie/'.$id.'/images', ['_csrf_token' => $this->token($client, $id)], ['image' => new UploadedFile($fake, 'image.png', 'image/png', null, true)]);
            $this->assertCount(1, $images->list($id));

            // Servie sans connexion, comme une image
            $client->restart();
            $client->request('GET', $path);
            $this->assertResponseIsSuccessful();
            $this->assertSame('image/webp', $client->getResponse()->headers->get('Content-Type'));
            $client->request('GET', '/academie/images/'.$id.'/ffffffffffffffff.webp');
            $this->assertResponseStatusCodeSame(404);
            $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

            // Utilisée dans une leçon : la suppression est refusée
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $course = $em->find(Course::class, $id);
            $course->getLessons()[0]->setContentMarkdown("Avant\n\n![Forêt]($path)");
            $em->flush();
            $client->request('POST', '/admin/academie/'.$id.'/images/'.$list[0]['name'].'/supprimer', ['_csrf_token' => $this->token($client, $id)]);
            $this->assertCount(1, $images->list($id));

            $course = $em->find(Course::class, $id);
            $course->getLessons()[0]->setContentMarkdown('Sans image');
            $em->flush();
            $client->request('POST', '/admin/academie/'.$id.'/images/'.$list[0]['name'].'/supprimer', ['_csrf_token' => $this->token($client, $id)]);
            $this->assertCount(0, $images->list($id));

            // Sans jeton : refusé
            $client->request('POST', '/admin/academie/'.$id.'/images', [], ['image' => $this->png()]);
            $this->assertResponseStatusCodeSame(403);
        } finally {
            $images->deleteAll($id);
        }
    }

    public function testLessonEditorOffersCalloutsDiagramsAndImages(): void
    {
        $client = $this->admin();
        $course = $this->makeCourse(published: false);
        $client->request('GET', '/admin/academie/lecon/'.$course->getLessons()[0]->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.ac-insert summary', 'Rendre la leçon plus visuelle');
        $this->assertSelectorExists('.ac-insert button:contains("À retenir")');
        $this->assertSelectorExists('.ac-insert button:contains("Les cinq principes de la fécondité")');
    }
}
