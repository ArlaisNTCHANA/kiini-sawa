<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Academie\Entity\CourseEnrollment;
use App\Academie\Entity\LessonNote;
use App\Admin\Entity\AppConfig;
use App\Boussole\Entity\BoussoleResult;
use App\Compte\Entity\User;
use App\Journal\Entity\ReflectionNote;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\Value;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'Académie côté membres (Lot 2) : un membre parcourt une leçon de bout en bout, répond aux questions, prend une
 * note (dans son Journal) et retrouve sa réponse dans son vrai plan, sans doublon ; droits PRO, parcours retiré,
 * recommandation de la Boussole 360, sécurité.
 */
class AcademyMemberTest extends WebTestCase
{
    use AccountTestTrait;
    use AcademyFixturesTrait;

    private function member(KernelBrowser $client, bool $pro = false): User
    {
        $user = $this->createUser(pro: $pro);
        $client->loginUser($user);

        return $user;
    }

    private function lessonUrl(Course $course, int $n = 1): string
    {
        return '/academie/'.$course->getSlug().'/lecon-'.$n;
    }

    private function submitPractice(KernelBrowser $client, Course $course, array $values, string $mode = 'plan', int $n = 1): void
    {
        $client->request('GET', $this->lessonUrl($course, $n));
        $this->assertResponseIsSuccessful();
        $form = $client->getCrawler()->filter('#pratique form')->selectButton(
            $client->getCrawler()->filter('#pratique button[value="'.$mode.'"]')->text()
        )->form();
        $client->submit($form, $values);
        $this->assertResponseRedirects();
    }

    private function countRows(string $class, array $criteria): int
    {
        $this->em()->clear();

        return $this->em()->getRepository($class)->count($criteria);
    }

    public function testCatalogShowsPublishedCoursesAndKeepsTheCoupleApart(): void
    {
        $client = static::createClient();
        $this->member($client);
        $published = $this->makeCourse('VIE');
        $draft = $this->makeCourse('CROISSANCE', published: false);
        $this->makeCourse('COUPLE', lessons: [['Leçon du couple', null, null]]);

        $crawler = $client->request('GET', '/academie');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('[data-course="'.$published->getSlug().'"]'));
        $this->assertCount(0, $crawler->filter('[data-course="'.$draft->getSlug().'"]'), 'Un brouillon n\'est pas au catalogue.');
        $filters = $crawler->filter('.academie-filters');
        foreach (['Plan de Vie', 'Plan de Croissance', 'Plan de Développement personnel', 'Plan d\'Hygiène de vie', 'Plan d\'Intendance', 'Plan de Transmission'] as $plan) {
            $this->assertStringContainsString($plan, $filters->text());
        }
        $this->assertCount(1, $filters->filter('.academie-filters__sep'), 'Le Sanctuaire du Couple est séparé des 6 plans.');
        $this->assertStringContainsString('Sanctuaire du Couple', $filters->filter('.academie-filters__sep ~ a')->text());

        $this->assertSelectorExists('#ks-top-nav a[href="/academie"]', 'L\'Académie est dans le menu (La Méthode).');
        $this->assertSelectorTextContains('#ks-top-nav .topnav-item.active', 'La Méthode');

        $crawler = $client->request('GET', '/academie?lieu=COUPLE');
        $this->assertCount(0, $crawler->filter('[data-course="'.$published->getSlug().'"]'));

        $client->request('GET', '/academie/'.$draft->getSlug());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testMemberGoesThroughALessonAndFindsTheAnswerInHisPlanWithoutDuplicate(): void
    {
        $client = static::createClient();
        $user = $this->member($client);
        $course = $this->makeCourse('VIE', lessons: [['Ce qui compte', 'VIE', 'add_value'], ['Passer à l\'action', null, null]]);

        $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertSelectorTextContains('.academie-cta', 'Commencer le parcours');

        $crawler = $client->request('GET', $this->lessonUrl($course));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#pratique', 'Enregistrer dans mon Plan de Vie');
        $this->assertSelectorTextContains('#pratique', 'Terminer la leçon sans modifier mon plan');
        $this->assertSame(1, $this->countRows(CourseEnrollment::class, ['user' => $user]), 'Ouvrir une leçon inscrit au parcours.');

        // Une note, classée sur un plan, va dans le Journal (carnet « Académie »)
        $client->submit($crawler->filter('#notes form')->form(), ['content' => 'Mon déclic du jour', 'plan' => 'INTENDANCE']);
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('#notes', 'Mon déclic du jour');
        $this->em()->clear();
        $note = $this->em()->getRepository(ReflectionNote::class)->findOneBy(['user' => $user, 'content' => 'Mon déclic du jour']);
        $this->assertNotNull($note);
        $this->assertSame('Académie', $note->getCollection()?->getName());
        $link = $this->em()->getRepository(LessonNote::class)->findOneBy(['note' => $note]);
        $this->assertSame('INTENDANCE', $link->getPlanKey());

        // Mettre en pratique : la valeur arrive dans le vrai Plan de Vie
        $this->submitPractice($client, $course, ['title' => 'Fidélité', 'detail' => 'Je tiens parole.']);
        $this->assertResponseRedirects($this->lessonUrl($course, 2), null, 'Après l\'exercice, la leçon suivante.');
        $this->em()->clear();
        $values = $this->em()->getRepository(Value::class)->findBy(['name' => 'Fidélité']);
        $values = array_values(array_filter($values, fn (Value $v) => $v->getPlanDeVie()?->getUser()?->getId() === $user->getId()));
        $this->assertCount(1, $values);
        $valueId = $values[0]->getId();
        $this->assertSame('Je tiens parole.', $values[0]->getBehavior());

        // Refaire l'exercice met à jour le même élément
        $this->submitPractice($client, $course, ['title' => 'Fidélité dans les petites choses', 'detail' => '']);
        $this->em()->clear();
        $value = $this->em()->find(Value::class, $valueId);
        $this->assertSame('Fidélité dans les petites choses', $value->getName());
        $this->assertSame(0, $this->countRows(Value::class, ['name' => 'Fidélité']), 'Pas de doublon.');
        $client->request('GET', $this->lessonUrl($course));
        $this->assertSelectorTextContains('#pratique', 'Mettre à jour dans mes plans');

        // Dernière leçon terminée sans rien modifier : parcours terminé
        $this->submitPractice($client, $course, [], 'sans', 2);
        $this->assertResponseRedirects('/academie/'.$course->getSlug());
        $client->followRedirect();
        $this->assertSelectorTextContains('.academie-cta', 'Vous avez terminé ce parcours');
        $this->assertSelectorTextContains('.academie-cta', 'Refaire ma Boussole 360', 'Pas de Boussole 360 récente : on la propose.');
        $this->assertSelectorTextContains('#notes-title', 'Mes notes de ce parcours');
    }

    public function testFreePointGoesToTheChosenPlan(): void
    {
        $client = static::createClient();
        $user = $this->member($client);
        $course = $this->makeCourse('VIE', lessons: [['Sans exercice', null, null]]);

        $this->submitPractice($client, $course, ['title' => 'Dire non aux urgences', 'destination' => 'VIE|add_life_goal']);
        $this->em()->clear();
        $goal = $this->em()->getRepository(LifeGoal::class)->findOneBy(['title' => 'Dire non aux urgences']);
        $this->assertNotNull($goal);
        $this->assertSame($user->getId(), $goal->getPlanDeVie()->getUser()->getId());
        $this->assertSame('DO', $goal->getCategory());

        // Une destination inventée est refusée
        $client->request('GET', $this->lessonUrl($course));
        $client->request('POST', $this->lessonUrl($course).'/terminer', [
            '_csrf_token' => $client->getCrawler()->filter('#pratique input[name="_csrf_token"]')->attr('value'),
            'mode' => 'plan', 'destination' => 'VIE|update_purpose', 'title' => 'Remplacer ma raison d\'être',
        ]);
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('.ks-alert, [role="alert"], body', 'Choisissez où enregistrer');
    }

    public function testProCourseIsReservedToProMembers(): void
    {
        $client = static::createClient();
        $this->member($client);
        $course = $this->makeCourse('INTENDANCE', free: false);

        $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.academie-cta', 'KIINI SAWA PRO');
        $client->request('GET', $this->lessonUrl($course));
        $this->assertResponseRedirects();
        $this->assertStringStartsWith('/abonnement', $client->getResponse()->headers->get('Location'));

        $this->member($client, pro: true);
        $client->request('GET', $this->lessonUrl($course));
        $this->assertResponseIsSuccessful();
    }

    public function testPlanRightsAreTheSameAsInTheRestOfTheApplication(): void
    {
        $client = static::createClient();
        $config = $this->em()->getRepository(AppConfig::class)->find('default');
        $before = $config->isEnableStewardshipFree();
        $config->setEnableStewardshipFree(false);
        $this->em()->flush();
        try {
            $free = $this->member($client);
            $course = $this->makeCourse('INTENDANCE', lessons: [['Gérer', 'INTENDANCE', 'add_stewardship_item']]);
            $client->request('GET', $this->lessonUrl($course));
            $this->assertSelectorTextContains('#pratique .ks-alert', 'fait partie de KIINI SAWA PRO');
            $this->assertSelectorNotExists('#pratique button[value="plan"]');

            // Forcer l'envoi ne crée rien
            $client->request('POST', $this->lessonUrl($course).'/terminer', [
                '_csrf_token' => $client->getCrawler()->filter('#pratique input[name="_csrf_token"]')->attr('value'),
                'mode' => 'plan', 'title' => 'Mon budget',
            ]);
            $this->assertSame(0, $this->countRows(StewardshipItem::class, ['user' => $free]));

            // La leçon peut toujours être terminée sans modifier le plan
            $this->submitPractice($client, $course, [], 'sans');
            $this->assertSame(1, $this->countRows(\App\Academie\Entity\LessonCompletion::class, ['lesson' => $course->getLessons()[0]->getId()]));

            $pro = $this->member($client, pro: true);
            $this->submitPractice($client, $course, ['title' => 'Mon budget', 'choice' => 'TEMPS']);
            $this->em()->clear();
            $item = $this->em()->getRepository(StewardshipItem::class)->findOneBy(['user' => $pro]);
            $this->assertSame('Mon budget', $item->getLabel());
            $this->assertSame('TEMPS', $item->getOrigin());
        } finally {
            $config = $this->em()->getRepository(AppConfig::class)->find('default');
            $config->setEnableStewardshipFree($before);
            $this->em()->flush();
        }
    }

    public function testUnpublishedCourseStaysOpenForThoseWhoStartedIt(): void
    {
        $client = static::createClient();
        $this->member($client);
        $course = $this->makeCourse('VIE');
        $client->request('GET', $this->lessonUrl($course));
        $this->assertResponseIsSuccessful();

        $this->em()->clear();
        $this->em()->find(Course::class, $course->getId())->unpublish();
        $this->em()->flush();

        $crawler = $client->request('GET', '/academie');
        $this->assertGreaterThan(0, $crawler->filter('[data-course="'.$course->getSlug().'"]')->count(), 'Toujours visible pour qui l\'a commencé.');
        $client->request('GET', '/academie/'.$course->getSlug());
        $this->assertSelectorTextContains('.ks-alert--info', 'en cours de révision');
        $client->request('GET', $this->lessonUrl($course));
        $this->assertResponseIsSuccessful();

        $this->member($client);
        $client->request('GET', $this->lessonUrl($course));
        $this->assertResponseStatusCodeSame(404, 'Invisible pour les autres.');
    }

    public function testBoussoleRecommendsTheCourseOfThePriorityPlan(): void
    {
        $client = static::createClient();
        $user = $this->member($client);
        $course = $this->makeCourse('INTENDANCE');
        $this->em()->persist(new BoussoleResult($user, array_fill(0, 24, 3), ['scores' => [], 'coherence' => 50, 'strongestKey' => 'VIE', 'priorityKey' => 'INTENDANCE'], 'web'));
        $this->em()->flush();

        $crawler = $client->request('GET', '/academie');
        $this->assertSelectorTextContains('.academie-reco', 'Recommandé d\'après votre Boussole 360 : priorité Plan d\'Intendance');
        // Le premier parcours publié du plan prioritaire (la base de test peut en contenir d'autres)
        $slug = $crawler->filter('.academie-reco [data-course]')->attr('data-course');
        $this->em()->clear();
        $this->assertSame('INTENDANCE', $this->em()->getRepository(Course::class)->findOneBy(['slug' => $slug])->getPrimaryPlan());
        $crawler = $client->request('GET', '/academie?lieu=INTENDANCE');
        $this->assertSelectorNotExists('.academie-reco', 'Pas de recommandation quand on filtre.');
        $this->assertCount(1, $crawler->filter('[data-course="'.$course->getSlug().'"]'));
    }

    public function testSecurity(): void
    {
        $client = static::createClient();
        $client->request('GET', '/academie');
        $this->assertResponseRedirects('/login');

        $owner = $this->member($client);
        $course = $this->makeCourse('VIE');
        $crawler = $client->request('GET', $this->lessonUrl($course));
        $client->submit($crawler->filter('#notes form')->form(), ['content' => 'Note privée']);
        $this->em()->clear();
        $link = $this->em()->getRepository(LessonNote::class)->findOneBy(['lesson' => $course->getLessons()[0]->getId()]);

        // Sans jeton : refusé
        $client->request('POST', $this->lessonUrl($course).'/terminer', ['mode' => 'sans']);
        $this->assertResponseStatusCodeSame(403);

        // Un autre membre ne peut pas supprimer la note
        $this->member($client);
        $crawler = $client->request('GET', $this->lessonUrl($course));
        $token = $crawler->filter('#notes input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/academie/notes/'.$link->getId().'/supprimer', ['_csrf_token' => $token]);
        $this->assertResponseStatusCodeSame(404);
        $this->assertSame(1, $this->countRows(LessonNote::class, ['id' => $link->getId()]));
        $this->assertSelectorNotExists('#notes .academie-note');

        // Son propriétaire, si
        $client->loginUser($owner);
        $crawler = $client->request('GET', $this->lessonUrl($course));
        $client->submit($crawler->filter('#notes .academie-note form')->form());
        $this->assertSame(0, $this->countRows(LessonNote::class, ['id' => $link->getId()]));
        $this->assertSame(0, $this->countRows(ReflectionNote::class, ['content' => 'Note privée', 'user' => $owner]));
    }
}
