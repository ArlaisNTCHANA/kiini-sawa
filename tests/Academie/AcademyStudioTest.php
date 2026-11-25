<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Academie\Entity\Lesson;
use App\Academie\Repository\CourseRepository;
use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Studio auteur de l'Académie (Lot 1) : le fondateur crée un parcours complet, le prévisualise, le publie et le repasse
 * en brouillon ; sécurité (accès administrateur, CSRF, texte et vidéos sans faille).
 */
class AcademyStudioTest extends WebTestCase
{
    private function admin(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    private function course(string $slug): Course
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        return static::getContainer()->get(CourseRepository::class)->findOneBy(['slug' => $slug]);
    }

    /** Envoie le formulaire dont l'adresse est $action, depuis la page affichée. */
    private function post(KernelBrowser $client, string $action, array $values = [], ?string $button = null): void
    {
        $form = $client->getCrawler()->filter('form[action="'.$action.'"]');
        $this->assertGreaterThan(0, $form->count(), 'Formulaire introuvable : '.$action);
        $f = null !== $button ? $form->filter('button[value="'.$button.'"]')->form() : $form->form();
        $client->submit($f, $values);
    }

    private function createCourse(KernelBrowser $client, string $title, string $place = 'INTENDANCE'): Course
    {
        $client->request('GET', '/admin/academie/nouveau');
        $this->assertResponseIsSuccessful();
        $slug = 'test-'.uniqid();
        $client->submitForm('Créer le parcours', ['title' => $title, 'slug' => $slug, 'subtitle' => 'Une accroche', 'description' => 'Pour **gérer** ce qui vous est confié.', 'place' => $place, 'access' => 'FREE']);
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('.ks-alert--success', 'Parcours créé');

        return $this->course($slug);
    }

    public function testOnlyAdministratorsOpenTheStudio(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/academie');
        $this->assertResponseRedirects('/login');

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $member = (new User())->setEmail('membre-academie-'.uniqid().'@exemple.org')->setPassword('non-utilise')->setIsVerified(true);
        $em->persist($member);
        $em->flush();
        $client->loginUser($member);
        $client->request('GET', '/admin/academie');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testFounderBuildsPreviewsPublishesAndUnpublishesACourse(): void
    {
        $client = $this->admin();
        $course = $this->createCourse($client, 'Gérer ce qui vous est confié');
        $this->assertFalse($course->isPublished(), 'Un nouveau parcours commence en brouillon.');
        $this->assertCount(1, $course->getModules(), 'Un premier module est créé avec le parcours.');
        $this->assertTrue($course->isFree());
        $this->assertSame('INTENDANCE', $course->getPrimaryPlan());

        // Publier sans leçon est refusé.
        $this->assertSelectorTextContains('#statut', 'Ajoutez au moins une leçon');

        // Deuxième module, puis renommage du premier.
        $this->post($client, '/admin/academie/'.$course->getId().'/modules', ['title' => 'Passer à l\'action']);
        $client->followRedirect();
        $module = $course->getModules()->first();
        $this->post($client, '/admin/academie/module/'.$module->getId(), ['title' => 'Faire l\'inventaire']);
        $client->followRedirect();
        $this->assertSelectorExists('input[value="Faire l\'inventaire"]');

        // Leçon avec texte, vidéo YouTube et exercice relié au Plan d'Intendance.
        $client->request('GET', '/admin/academie/module/'.$module->getId().'/lecon');
        $client->submitForm('Créer la leçon', [
            'title' => 'Ce qui vous est confié', 'duration' => 8,
            'content' => "## Tout vient de Dieu\n\nVous êtes **intendant**, pas propriétaire.",
            'video' => 'https://youtu.be/dQw4w9WgXcQ?si=abc', 'practice' => 'INTENDANCE|add_stewardship_item',
            'prompt' => 'Nommez une ressource qui dort.', 'visible' => '1',
        ]);
        $this->assertResponseRedirects();
        $client->followRedirect();
        $lesson = $this->course($course->getSlug())->getLessons()[0];
        $this->assertSame(Lesson::MEDIA_YOUTUBE, $lesson->getMediaProvider());
        $this->assertSame('dQw4w9WgXcQ', $lesson->getMediaRef());
        $this->assertSame('ce-qui-vous-est-confie', $lesson->getSlug());
        $this->assertSame('add_stewardship_item', $lesson->getTargetAction());

        // Question de discernement : deux réponses justes sur trois.
        $client->request('GET', '/admin/academie/lecon/'.$lesson->getId().'/question');
        $client->submitForm('Enregistrer la question', [
            'question' => 'Votre salaire arrive. Que faites-vous en premier ?',
            'options' => ['Je mets de côté ce que j\'ai décidé de donner', 'Je dépense, on verra', 'Je mets de côté mon épargne', ''],
            'correct' => [0 => '1', 2 => '1'],
            'explanation' => 'Décider avant de dépenser, c\'est gérer.',
        ]);
        $this->assertResponseRedirects();
        $question = $this->course($course->getSlug())->getLessons()[0]->getQuestions()->first();
        $this->assertCount(3, $question->getOptions(), 'Les réponses vides sont ignorées.');
        $this->assertSame(['a', 'c'], $question->getCorrectOptionIds());
        $this->assertTrue($question->isMultiple());

        // Aperçu comme un apprenant : parcours et leçon.
        $client->request('GET', '/admin/academie/'.$course->getId().'/apercu');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Gérer ce qui vous est confié');
        $this->assertSelectorTextContains('.academie-banner', 'brouillon');
        $client->request('GET', '/admin/academie/lecon/'.$lesson->getId().'/apercu');
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('<h2>Tout vient de Dieu</h2>', $html);
        $this->assertStringContainsString('data-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0&amp;autoplay=1"', $html, 'YouTube passe par la version sans cookie.');
        $this->assertStringNotContainsString('<iframe src=', $html, 'Le lecteur ne se charge qu\'au clic.');
        $this->assertSelectorTextContains('.academie-question', 'Votre salaire arrive');
        $this->assertSelectorTextContains('.ks-card--accent', 'Enregistrer dans mon Plan d\'Intendance');

        // Publier, puis repasser en brouillon.
        $client->request('GET', '/admin/academie/'.$course->getId());
        $this->post($client, '/admin/academie/'.$course->getId().'/statut', [], 'publier');
        $client->followRedirect();
        $this->assertSelectorTextContains('.ks-alert--success', 'Parcours publié');
        $this->assertTrue($this->course($course->getSlug())->isPublished());
        $this->post($client, '/admin/academie/'.$course->getId().'/statut', [], 'retirer');
        $client->followRedirect();
        $this->assertSelectorTextContains('.ks-alert--success', 'gardent leur progression');
        $reloaded = $this->course($course->getSlug());
        $this->assertFalse($reloaded->isPublished());
        $this->assertTrue($reloaded->wasEverPublished());
        $this->assertCount(1, $reloaded->getLessons(), 'Retirer un parcours ne supprime rien.');

        // Déjà publié une fois : ni le parcours ni ses leçons ne peuvent être supprimés.
        $client->request('GET', '/admin/academie/'.$course->getId());
        $this->assertSelectorNotExists('form[action="/admin/academie/'.$course->getId().'/supprimer"]');
        $token = $client->getCrawler()->filter('input[name="_csrf_token"]')->first()->attr('value');
        $client->request('POST', '/admin/academie/'.$course->getId().'/supprimer', ['_csrf_token' => $token]);
        $client->followRedirect();
        $this->assertSelectorTextContains('.ks-alert--danger', 'déjà été publié');
        $this->assertNotNull($this->course($course->getSlug()));
    }

    public function testNewLessonInAPublishedCourseStaysHiddenUntilReady(): void
    {
        $client = $this->admin();
        $course = $this->createCourse($client, 'Parcours déjà en ligne', 'VIE');
        $module = $course->getModules()->first();
        $client->request('GET', '/admin/academie/module/'.$module->getId().'/lecon');
        $client->submitForm('Créer la leçon', ['title' => 'Première', 'duration' => 5, 'content' => 'Un texte.', 'visible' => '1']);
        $client->request('GET', '/admin/academie/'.$course->getId());
        $this->post($client, '/admin/academie/'.$course->getId().'/statut', [], 'publier');

        $client->request('GET', '/admin/academie/module/'.$module->getId().'/lecon');
        $this->assertSelectorNotExists('input[name="visible"]:checked');
        $client->submitForm('Créer la leçon', ['title' => 'Deuxième en préparation', 'duration' => 5, 'content' => 'Brouillon.']);
        $lessons = $this->course($course->getSlug())->getLessons();
        $this->assertCount(2, $lessons);
        $this->assertFalse($lessons[1]->isVisible());
        $this->assertCount(1, $this->course($course->getSlug())->getLessons(true));
    }

    public function testLessonsCanBeReorderedAndDeletedWhileTheCourseWasNeverPublished(): void
    {
        $client = $this->admin();
        $course = $this->createCourse($client, 'Parcours à réordonner', 'COUPLE');
        $this->assertTrue($course->isCouple(), 'Le Sanctuaire du Couple est rangé à part des 6 plans.');
        $this->assertNull($course->getPrimaryPlan());
        $module = $course->getModules()->first();
        foreach (['Un', 'Deux', 'Trois'] as $title) {
            $client->request('GET', '/admin/academie/module/'.$module->getId().'/lecon');
            $client->submitForm('Créer la leçon', ['title' => $title, 'duration' => 5, 'content' => 'Texte.']);
        }
        $ids = array_map(fn (Lesson $l) => $l->getId(), $this->course($course->getSlug())->getLessons());
        $client->request('GET', '/admin/academie/'.$course->getId());
        $this->post($client, '/admin/academie/lecon/'.$ids[2].'/deplacer', [], 'monter');
        $titles = array_map(fn (Lesson $l) => $l->getTitle(), $this->course($course->getSlug())->getLessons());
        $this->assertSame(['Un', 'Trois', 'Deux'], $titles);

        $client->request('GET', '/admin/academie/lecon/'.$ids[0]);
        $this->post($client, '/admin/academie/lecon/'.$ids[0].'/supprimer');
        $titles = array_map(fn (Lesson $l) => $l->getTitle(), $this->course($course->getSlug())->getLessons());
        $this->assertSame(['Trois', 'Deux'], $titles);
    }

    public function testLessonTextCannotInjectHtmlNorScripts(): void
    {
        $client = $this->admin();
        $course = $this->createCourse($client, 'Parcours sûr', 'VIE');
        $module = $course->getModules()->first();
        $client->request('GET', '/admin/academie/module/'.$module->getId().'/lecon');
        $client->submitForm('Créer la leçon', ['title' => 'Essai', 'duration' => 5, 'content' => "<script>alert(1)</script>\n\n[piège](javascript:alert(2)) <img src=x onerror=alert(3)>"]);
        $lesson = $this->course($course->getSlug())->getLessons()[0];
        $client->request('GET', '/admin/academie/lecon/'.$lesson->getId().'/apercu');
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('href="javascript:', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testUnknownVideoLinksAreRefused(): void
    {
        $client = $this->admin();
        $course = $this->createCourse($client, 'Parcours vidéo', 'VIE');
        $client->request('GET', '/admin/academie/module/'.$course->getModules()->first()->getId().'/lecon');
        $client->submitForm('Créer la leçon', ['title' => 'Vidéo piégée', 'duration' => 5, 'content' => 'Texte.', 'video' => 'https://youtube.com.exemple.org/watch?v=dQw4w9WgXcQ']);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.ks-alert--danger', 'Lien vidéo non reconnu');
        $this->assertCount(0, $this->course($course->getSlug())->getLessons());
    }

    public function testEveryChangeNeedsTheCsrfToken(): void
    {
        $client = $this->admin();
        $course = $this->createCourse($client, 'Parcours protégé', 'VIE');
        $client->request('POST', '/admin/academie/'.$course->getId().'/statut', ['action' => 'publier', '_csrf_token' => 'faux']);
        $this->assertResponseStatusCodeSame(403);
        $client->request('POST', '/admin/academie/'.$course->getId().'/modules', ['title' => 'Intrus']);
        $this->assertResponseStatusCodeSame(403);
        $this->assertCount(1, $this->course($course->getSlug())->getModules());
    }

    public function testMarkdownPreviewUsesTheSafeRendering(): void
    {
        $client = $this->admin();
        $client->request('GET', '/admin/academie/nouveau');
        $token = $client->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/admin/academie/apercu-texte', ['_csrf_token' => $token, 'content' => "**Gras** <b>brut</b>"]);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('<strong>Gras</strong>', (string) $client->getResponse()->getContent());
        $this->assertStringContainsString('&lt;b&gt;', (string) $client->getResponse()->getContent());
    }

    public function testVideoPlayersAreTheOnlyFramesAllowed(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-src https://www.youtube-nocookie.com https://player.vimeo.com", $csp);
        $this->assertStringNotContainsString('https://www.youtube.com', $csp);
    }

    public function testStudioComfortFeaturesWork(): void
    {
        $client = $this->admin();
        $course = $this->createCourse($client, 'Parcours confort', 'VIE');
        $moduleId = $course->getModules()->first()->getId();

        // Créer deux leçons pour vérifier la navigation Précédente / Suivante
        foreach (['Première leçon', 'Deuxième leçon'] as $title) {
            $client->request('GET', '/admin/academie/module/'.$moduleId.'/lecon');
            $token = $client->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
            $client->request('POST', '/admin/academie/module/'.$moduleId.'/lecon', [
                '_csrf_token' => $token, 'title' => $title, 'module' => $moduleId, 'duration' => 6,
                'editor' => 'texte', 'content' => 'Contenu de '.$title, 'visible' => '1',
            ]);
            $this->assertResponseRedirects();
        }
        $lessons = $this->course($course->getSlug())->getLessons();
        $this->assertCount(2, $lessons);

        $client->request('GET', '/admin/academie/lecon/'.$lessons[0]->getId());
        $this->assertResponseIsSuccessful();
        $html1 = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('Leçon 1 sur 2', $html1);
        $this->assertStringContainsString('/admin/academie/lecon/'.$lessons[1]->getId(), $html1);

        // Liste JSON des schémas pour actualisation sans recharger
        $client->request('GET', '/admin/academie/schemas-liste');
        $this->assertResponseIsSuccessful();
        $json = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertIsArray($json['diagrams'] ?? null);

        // Envoi d'image en JSON directement depuis une leçon (sans fichier -> 422 JSON)
        $token = $client->getCrawler()->filter('input[name="_csrf_token"]')->count()
            ? $client->getCrawler()->filter('input[name="_csrf_token"]')->attr('value')
            : null;
        if (null === $token) {
            $client->request('GET', '/admin/academie/'.$course->getId());
            $token = $client->getCrawler()->filter('input[name="_csrf_token"]')->attr('value');
        }
        $client->request('POST', '/admin/academie/'.$course->getId().'/images', ['_csrf_token' => $token], [], ['HTTP_ACCEPT' => 'application/json']);
        $this->assertResponseStatusCodeSame(422);
        $err = json_decode((string) $client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $err);
    }
}
