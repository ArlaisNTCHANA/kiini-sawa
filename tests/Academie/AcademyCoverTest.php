<?php

namespace App\Tests\Academie;

use App\Academie\Command\ImportCourseCommand;
use App\Academie\Entity\Course;
use App\Academie\Entity\CourseModule;
use App\Academie\Entity\Lesson;
use App\Academie\Repository\CourseRepository;
use App\Academie\Service\AcademyImages;
use App\Academie\Service\AcademyOverview;
use App\Compte\Repository\UserRepository;
use App\Tests\Compte\AccountTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Vignettes et page de présentation des parcours : plans couverts déduits des exercices (et ajoutés dans le studio),
 * Sanctuaire du Couple à part, cartes à vignette, programme détaillé, vignette envoyée dans le studio (types contrôlés,
 * jamais d'adresse externe), et vignette indiquée dans le fichier YAML.
 */
class AcademyCoverTest extends WebTestCase
{
    use AccountTestTrait;
    use AcademyFixturesTrait;

    private function admin(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    private function png(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'vig').'.png';
        $im = imagecreatetruecolor(800, 450);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 150, 46));
        imagepng($im, $path);

        return new UploadedFile($path, 'vignette.png', 'image/png', null, true);
    }

    public function testPlansAreDeducedFromExercisesAndTheCoupleStaysApart(): void
    {
        $course = (new Course())->setTitle('Essai')->setSlug('essai')->setPlace('HYGIENE')->setExtraPlans(['TRANSMISSION', 'INCONNU', 'COUPLE']);
        $m1 = (new CourseModule())->setTitle('Un');
        $m2 = (new CourseModule())->setTitle('Deux');
        $m3 = (new CourseModule())->setTitle('Vide');
        $course->addModule($m1);
        $course->addModule($m2);
        $course->addModule($m3);
        $make = fn (string $t, ?string $target, ?string $action, int $min, bool $visible = true) => (new Lesson())->setTitle($t)->setSlug($t)
            ->setContentMarkdown('x')->setDurationMinutes($min)->setVisible($visible)->setPractice($target, $action);
        $m1->addLesson($make('a', 'VIE', 'add_life_goal', 6));
        $m1->addLesson($make('b', 'RITUEL', 'add_habit', 9));
        $m2->addLesson($make('c', 'JOURNAL', 'add_note', 7));
        $m2->addLesson($make('d', 'COUPLE', 'add_couple_item', 8));
        $m2->addLesson($make('e', 'INTENDANCE', 'add_stewardship_item', 5, false));
        $m3->addLesson($make('f', null, null, 5, false));

        $this->assertSame(['TRANSMISSION'], $course->getExtraPlans(), 'Seules les clés des 6 plans sont gardées.');
        $covered = AcademyOverview::coveredPlans($course);
        $this->assertSame(['HYGIENE', 'VIE', 'TRANSMISSION'], array_column($covered, 'key'), 'Plan principal d\'abord ; la leçon masquée ne compte pas.');
        $this->assertTrue($covered[0]['main']);
        $this->assertSame('Hygiène de vie', $covered[0]['short']);
        $this->assertSame(1, $covered[1]['exercises']);
        $six = AcademyOverview::plans($course);
        $this->assertSame(['VIE', 'CROISSANCE', 'DEVELOPPEMENT', 'HYGIENE', 'INTENDANCE', 'TRANSMISSION'], array_column($six, 'key'), 'Ordre du livre');
        $this->assertSame(['rituels' => 1, 'journal' => 1, 'couple' => 1], AcademyOverview::daily($course));

        $stats = AcademyOverview::stats($course);
        $this->assertSame(['modules' => 2, 'lessons' => 4, 'minutes' => 30, 'min_lesson' => 6, 'max_lesson' => 9], array_intersect_key($stats, array_flip(['modules', 'lessons', 'minutes', 'min_lesson', 'max_lesson'])));
        $programme = AcademyOverview::programme($course);
        $this->assertCount(2, $programme, 'Un module sans leçon visible n\'apparaît pas.');
        $this->assertSame([3, 4], array_column($programme[1]['lessons'], 'n'), 'Leçons numérotées sur tout le parcours.');
        $this->assertSame('couple', AcademyOverview::lessonActivities($m2->getLessons()[1])['target_kind']);
        $this->assertSame('2 h 33', AcademyOverview::duration(153));
        $this->assertSame('50 min', AcademyOverview::duration(50));
    }

    public function testCatalogCardsAndPresentationPageAreVisual(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $course = $this->makeCourse('HYGIENE', lessons: [['Première leçon', 'VIE', 'add_value'], ['Deuxième', 'RITUEL', 'add_habit'], ['Troisième', null, null]]);
        $course->setCoverWord('Fécondité');
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        $crawler = $client->request('GET', '/academie');
        $this->assertResponseIsSuccessful();
        $card = $crawler->filter('.academie-card[data-course="'.$course->getSlug().'"]');
        $this->assertCount(1, $card->filter('.academie-vignette__dessin'), 'Sans image : vignette dessinée, aucune image externe.');
        $this->assertStringContainsString('Fécondité', $card->filter('.academie-vignette__mot')->text());
        $this->assertStringContainsString('Gratuit', $card->filter('.academie-vignette__acces')->text());
        $this->assertSame(['Hygiène de vie', 'Vie'], $card->filter('.academie-pastille')->each(fn ($n) => trim($n->text())));

        $crawler = $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.academie-hero');
        $this->assertSelectorTextContains('[data-plans] .academie-mainplan', 'Plan d\'Hygiène de vie');
        $this->assertCount(6, $crawler->filter('.academie-six li'));
        $this->assertCount(4, $crawler->filter('.academie-six li.is-off'));
        $this->assertSelectorTextContains('[data-plans] .academie-also', 'Rituels');
        $this->assertCount(3, $crawler->filter('[data-programme] .academie-lesson'));
        $this->assertSelectorTextContains('[data-programme]', 'Exercice · Plan de Vie');
        $this->assertCount(1, $crawler->filter('article .ks-btn--primary'), 'Une seule action principale.');
        $this->assertSelectorTextContains('article .ks-btn--primary', 'Commencer le parcours');
        $this->assertSelectorExists('#rythme', 'Le rythme est toujours proposé.');

        // Après une leçon : progression et « Reprendre »
        $crawler = $client->request('GET', '/academie/'.$course->getSlug().'/lecon-1');
        $token = (string) $crawler->filter('#pratique input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/academie/'.$course->getSlug().'/lecon-1/terminer', ['_csrf_token' => $token, 'mode' => 'sans']);
        $crawler = $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertSelectorTextContains('article .ks-btn--primary', 'Reprendre le parcours');
        $this->assertSelectorTextContains('.academie-cta', 'Deuxième');
        $this->assertCount(1, $crawler->filter('.academie-lesson__n.is-done'));
        $this->assertCount(1, $crawler->filter('.academie-lesson__n.is-next'));
    }

    public function testCoupleCourseShowsTheSanctuaryInFull(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $course = $this->makeCourse('COUPLE', lessons: [['Ensemble', 'COUPLE', 'add_couple_item']]);
        $crawler = $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-plans] .academie-mainplan', 'Sanctuaire du Couple');
        $this->assertCount(6, $crawler->filter('.academie-six li.is-off'), 'Le Sanctuaire du Couple n\'est pas un 7e plan.');
        $this->assertDoesNotMatchRegularExpression('/>\s*Sanctuaire\s*</u', (string) $client->getResponse()->getContent(), 'Jamais « Sanctuaire » seul.');
    }

    public function testStudioCoverUploadChoiceAndPlans(): void
    {
        $client = $this->admin();
        $course = $this->makeCourse('VIE', published: false);
        $id = (int) $course->getId();
        $images = static::getContainer()->get(AcademyImages::class);
        try {
            $crawler = $client->request('GET', '/admin/academie/'.$id);
            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('#vignette [data-cover-preview] .academie-card');
            $token = (string) $crawler->filter('#vignette input[name="_csrf_token"]')->attr('value');

            // Sans jeton : refusé
            $client->request('POST', '/admin/academie/'.$id.'/vignette', ['coverWord' => 'Non']);
            $this->assertResponseStatusCodeSame(403);

            $client->request('POST', '/admin/academie/'.$id.'/vignette', ['_csrf_token' => $token, 'coverWord' => '  Raison d\'être ', 'plans' => ['TRANSMISSION', 'COUPLE', 'X']], ['cover' => $this->png()]);
            $this->assertResponseRedirects();
            $em = static::getContainer()->get(EntityManagerInterface::class);
            $em->clear();
            $course = $em->find(Course::class, $id);
            $this->assertNotNull($course->getCoverImage());
            $this->assertNotNull($images->file($id, $course->getCoverImage()), 'Image réenregistrée dans le dossier du parcours.');
            $this->assertSame('image/webp', getimagesize($images->file($id, $course->getCoverImage()))['mime']);
            $this->assertSame('Raison d\'être', $course->getCoverWord());
            $this->assertSame(['TRANSMISSION'], $course->getExtraPlans());

            // Utilisée comme vignette : la suppression est refusée
            $client->request('POST', '/admin/academie/'.$id.'/images/'.$course->getCoverImage().'/supprimer', ['_csrf_token' => $token]);
            $this->assertNotNull($images->file($id, $course->getCoverImage()));

            // Une « image » qui n'en est pas une est refusée, la vignette reste
            $fake = tempnam(sys_get_temp_dir(), 'txt');
            file_put_contents($fake, '<?php echo "non";');
            $client->request('POST', '/admin/academie/'.$id.'/vignette', ['_csrf_token' => $token, 'image' => $course->getCoverImage()], ['cover' => new UploadedFile($fake, 'image.png', 'image/png', null, true)]);
            $em->clear();
            $this->assertSame($course->getCoverImage(), $em->find(Course::class, $id)->getCoverImage());

            // L'aperçu du parcours montre l'image ; la carte du studio aussi
            $client->request('GET', '/admin/academie/'.$id.'/apercu');
            $this->assertSelectorExists('.academie-hero img[src="'.AcademyImages::publicPath($id, $course->getCoverImage()).'"]');

            // Un nom inventé est ignoré ; un choix vide revient à la vignette dessinée
            $client->request('POST', '/admin/academie/'.$id.'/vignette', ['_csrf_token' => $token, 'image' => 'ffffffffffffffff']);
            $em->clear();
            $this->assertSame($course->getCoverImage(), $em->find(Course::class, $id)->getCoverImage());
            $client->request('POST', '/admin/academie/'.$id.'/vignette', ['_csrf_token' => $token, 'image' => '']);
            $em->clear();
            $this->assertNull($em->find(Course::class, $id)->getCoverImage());
        } finally {
            $images->deleteAll($id);
        }
    }

    public function testYamlCoverMustBeALocalImage(): void
    {
        $dir = sys_get_temp_dir().'/academie-'.bin2hex(random_bytes(4));
        mkdir($dir);
        foreach (['https://exemple.com/photo.jpg', '//exemple.com/photo.jpg', '/etc/passwd.png', '../secret.png', 'texte.txt', 'absente.png'] as $bad) {
            try {
                ImportCourseCommand::coverFile(['cover' => $bad], $dir);
                $this->fail('Vignette refusée attendue : '.$bad);
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertNull(ImportCourseCommand::coverFile([], $dir));

        $this->expectException(\InvalidArgumentException::class);
        ImportCourseCommand::build(['title' => 'T', 'place' => 'VIE', 'plans' => ['SANCTUAIRE'], 'modules' => [['lessons' => [['title' => 'L', 'content' => 'x']]]]]);
    }

    public function testImportLoadsTheCoverWordAndPlans(): void
    {
        self::bootKernel();
        $dir = sys_get_temp_dir().'/academie-'.bin2hex(random_bytes(4));
        mkdir($dir);
        $png = $this->png();
        copy($png->getPathname(), $dir.'/vignette.png');
        $slug = 'import-vignette-'.bin2hex(random_bytes(3));
        file_put_contents($dir.'/parcours.yaml', "slug: $slug\ntitle: Import vignette\nplace: CROISSANCE\ncover: vignette.png\ncover_word: Engagement\nplans: [VIE]\nmodules:\n  - title: Un\n    lessons:\n      - title: Leçon\n        content: Texte\n");
        $tester = new CommandTester((new Application(self::$kernel))->find('app:academie:import'));
        $this->assertSame(0, $tester->execute(['fichier' => $dir.'/parcours.yaml']));
        $course = static::getContainer()->get(CourseRepository::class)->findOneBy(['slug' => $slug]);
        $images = static::getContainer()->get(AcademyImages::class);
        try {
            $this->assertSame('Engagement', $course->getCoverWord());
            $this->assertSame(['VIE'], $course->getExtraPlans());
            $this->assertNotNull($course->getCoverImage());
            $this->assertNotNull($images->coverUrl($course));
        } finally {
            $images->deleteAll((int) $course->getId());
        }
    }
}
