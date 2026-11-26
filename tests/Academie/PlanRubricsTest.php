<?php

namespace App\Tests\Academie;

use App\Academie\Entity\Course;
use App\Compte\Entity\User;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Entity\Value;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcours de plan (parcours guidés, 26/11/2026) : une séance écrit dans une rubrique entière du Plan de Vie, en
 * passant par l'assistant des plans. Elle ajoute ; elle ne retire ni ne modifie ce qui y est déjà.
 */
final class PlanRubricsTest extends WebTestCase
{
    use AccountTestTrait;
    use AcademyFixturesTrait;

    /** Les parcours de plan de ces tests repassent en brouillon : publiés, ils changeraient « Ma prochaine étape » ailleurs. */
    protected function tearDown(): void
    {
        static::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->getConnection()
            ->executeStatement("UPDATE academy_course SET status = 'DRAFT' WHERE kind = 'PLAN'");
        parent::tearDown();
    }

    private function planCourse(string $action): Course
    {
        $course = $this->makeCourse('VIE', true, true, [['Séance', 'VIE', $action]]);
        $course->setKind(Course::KIND_PLAN);
        $this->em()->flush();

        return $course;
    }

    private function finish(KernelBrowser $client, Course $course, array $fields): void
    {
        $crawler = $client->request('GET', '/academie/'.$course->getSlug().'/lecon-1');
        $this->assertResponseIsSuccessful();
        $client->request('POST', '/academie/'.$course->getSlug().'/lecon-1/terminer', [
            '_csrf_token' => $crawler->filter('#pratique input[name="_csrf_token"]')->attr('value'),
            'mode' => 'plan',
        ] + $fields);
    }

    private function reload(User $user): User
    {
        $this->em()->clear();

        return $this->findUser((string) $user->getEmail());
    }

    public function testValuesSessionAddsWithoutRemovingWhatIsThere(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $plan = new PlanDeVie();
        $user->setPlanDeVie($plan);
        $this->em()->persist($plan);
        $foi = (new Value())->setPlanDeVie($plan)->setName('Foi')->setBehavior('Je prie chaque matin')->setCategory('real');
        $declared = (new Value())->setPlanDeVie($plan)->setName('Réussite')->setBehavior('')->setCategory('declared');
        $this->em()->persist($foi);
        $this->em()->persist($declared);
        $this->em()->flush();
        $foiId = $foi->getId();
        $this->em()->clear(); // les collections du plan sont relues depuis la base, comme dans une vraie visite
        $user = $this->reload($user);
        $client->loginUser($user);
        $course = $this->planCourse('list_values');

        $crawler = $client->request('GET', '/academie/'.$course->getSlug().'/lecon-1');
        $this->assertSelectorTextContains('#pratique .academie-seance-deja', 'Foi');
        $this->assertCount(3, $crawler->filter('#pratique input[name="items[]"]'));

        $this->finish($client, $course, ['items' => ['Intégrité', ' foi ', '', 'Service']]);
        $this->assertResponseRedirects();

        $values = $this->reload($user)->getPlanDeVie()->getValues()->toArray();
        $names = array_map(fn (Value $v) => $v->getName().'/'.$v->getCategory(), $values);
        sort($names);
        $this->assertSame(['Foi/real', 'Intégrité/real', 'Réussite/declared', 'Service/real'], $names);
        $kept = array_values(array_filter($values, fn (Value $v) => 'Foi' === $v->getName()))[0];
        $this->assertSame($foiId, $kept->getId(), 'La valeur déjà posée garde son identifiant (et ses liens).');
        $this->assertSame('Je prie chaque matin', $kept->getBehavior());
    }

    public function testNothingNewIsRefusedAndTheLessonStaysOpen(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $course = $this->planCourse('list_passions');

        $this->finish($client, $course, ['items' => ['', '  ']]);
        $this->assertResponseRedirects('/academie/'.$course->getSlug().'/lecon-1#pratique');
        $this->assertNull($this->reload($user)->getPlanDeVie()?->getPassions()->count() ?: null);
    }

    public function testPurposeAndVisionAreWrittenInThePlan(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $this->finish($client, $this->planCourse('write_purpose'), ['text' => 'Aider les familles à grandir dans la foi.']);
        $this->finish($client, $this->planCourse('write_vision'), ['text' => 'Un foyer ouvert aux jeunes.', 'horizon' => '10 ans']);

        $plan = $this->reload($user)->getPlanDeVie();
        $this->assertSame('Aider les familles à grandir dans la foi.', $plan->getPurpose()?->getStatement());
        $this->assertSame('Un foyer ouvert aux jeunes.', $plan->getVision()?->getContent());
        $this->assertSame('10 ans', $plan->getVision()?->getHorizon());
    }

    public function testGoalsSessionKeepsExistingDeadlines(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $plan = new PlanDeVie();
        $user->setPlanDeVie($plan);
        $this->em()->persist($plan);
        $goal = (new LifeGoal())->setPlanDeVie($plan)->setTitle('Courir un semi-marathon')->setCategory('DO')->setProgress(40)
            ->setDeadline(new \DateTime('2027-06-30'));
        $this->em()->persist($goal);
        $this->em()->flush();
        $user = $this->reload($user);
        $client->loginUser($user);

        $this->finish($client, $this->planCourse('list_life_goals'), ['goals' => ['BE' => ['Être patient'], 'GIVE' => ['Parrainer un enfant', '']]]);

        $goals = $this->reload($user)->getPlanDeVie()->getLifeGoals()->toArray();
        $byTitle = [];
        foreach ($goals as $g) {
            $byTitle[$g->getTitle()] = $g;
        }
        $this->assertSame(['Courir un semi-marathon', 'Être patient', 'Parrainer un enfant'], array_values(array_intersect(['Courir un semi-marathon', 'Être patient', 'Parrainer un enfant'], array_keys($byTitle))));
        $this->assertCount(3, $goals);
        $this->assertSame('2027-06-30', $byTitle['Courir un semi-marathon']->getDeadline()?->format('Y-m-d'));
        $this->assertSame(40, $byTitle['Courir un semi-marathon']->getProgress());
        $this->assertSame('BE', $byTitle['Être patient']->getCategory());
        $this->assertSame('GIVE', $byTitle['Parrainer un enfant']->getCategory());
    }

    public function testMeditationCalloutIsAnInvitation(): void
    {
        $html = \App\Academie\Service\LessonMarkdown::toHtml(":::mediter\n> « Garde ton cœur plus que toute autre chose. » (Proverbes 4:23)\n:::");
        $this->assertStringContainsString('academie-encadre--mediter', $html);
        $this->assertStringContainsString('J\'ai pris ce temps', $html);
    }

    public function testStationsAdvanceAndTheEndLeadsToThePlan(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $course = $this->makeCourse('VIE', true, true, [['Vos passions', 'VIE', 'list_passions'], ['Vos dons', 'VIE', 'list_talents']]);
        $course->setKind(Course::KIND_PLAN);
        $this->em()->flush();

        $crawler = $client->request('GET', '/academie/'.$course->getSlug().'/lecon-1');
        $this->assertSelectorTextContains('.academie-metro__resume', 'Station 1 sur 2');
        $this->assertCount(2, $crawler->filter('.academie-metro__station'));
        $client->request('POST', '/academie/'.$course->getSlug().'/lecon-1/terminer', [
            '_csrf_token' => $crawler->filter('#pratique input[name="_csrf_token"]')->attr('value'),
            'mode' => 'plan', 'items' => ['Chanter'],
        ]);
        $this->assertResponseRedirects('/academie/'.$course->getSlug().'/lecon-2');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Station 1 sur 2 franchie');
        $this->assertSelectorExists('.academie-metro__station.is-done');

        $crawler = $client->getCrawler();
        $client->request('POST', '/academie/'.$course->getSlug().'/lecon-2/terminer', [
            '_csrf_token' => $crawler->filter('#pratique input[name="_csrf_token"]')->attr('value'),
            'mode' => 'plan', 'items' => ['Encourager'],
        ]);
        $this->assertResponseRedirects('/plan-de-vie');

        $enrollment = static::getContainer()->get(\App\Academie\Repository\CourseEnrollmentRepository::class)->findFor($this->reload($user), $this->em()->find(Course::class, $course->getId()));
        $this->assertSame(\App\Academie\Entity\CourseEnrollment::PACE_DAILY, $enrollment->getPaceMode(), 'Une séance par jour par défaut.');
    }

    public function testNewcomerIsOfferedWhereToStartThenResumes(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $course = $this->makeCourse('VIE', true, true, [['Vos passions', 'VIE', 'list_passions'], ['Vos dons', 'VIE', 'list_talents']]);
        $course->setKind(Course::KIND_PLAN);
        $this->em()->flush();

        $client->request('GET', '/dashboard');
        $this->assertSelectorExists('[data-next-step="plan-journey-start"]');
        $this->assertSelectorNotExists('[data-next-step="foundation"]');

        $client->request('GET', '/academie/par-ou-commencer');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-course="'.$course->getSlug().'"]');
        $this->assertSelectorTextContains('body', 'Je remplis seul');

        $client->request('GET', '/academie');
        $this->assertSelectorExists('#parcours-de-plan [data-course="'.$course->getSlug().'"]');

        $crawler = $client->request('GET', '/academie/'.$course->getSlug().'/lecon-1');
        $client->request('POST', '/academie/'.$course->getSlug().'/lecon-1/terminer', [
            '_csrf_token' => $crawler->filter('#pratique input[name="_csrf_token"]')->attr('value'),
            'mode' => 'sans',
        ]);
        $client->request('GET', '/dashboard');
        $this->assertSelectorExists('[data-next-step="plan-journey"]');
        $this->assertSelectorTextContains('[data-next-step="plan-journey"]', 'Vos dons');
        $this->assertSelectorTextContains('[data-next-step="plan-journey"]', 'station 2 sur 2');
        $this->assertSelectorTextNotContains('[data-next-step="plan-journey"]', 'Commencer mon parcours', 'Pas d\'invitation au parcours de 90 jours en même temps.');
    }

    public function testDailyEmailAnnouncesTheSessionWithWhatWasPosedAndItsVerse(): void
    {
        static::createClient();
        $user = $this->createUser();
        $course = $this->makeCourse('VIE', true, true, [['Vos passions', 'VIE', 'list_passions'], ['Votre vision', 'VIE', 'write_vision']]);
        $course->setKind(Course::KIND_PLAN);
        $course->getLessons()[1]->setContentMarkdown("Texte.\n\n:::mediter\n> « Écris la prophétie. » Habacuc 2:2\n\nUne prière.\n:::");
        $enrollment = new \App\Academie\Entity\CourseEnrollment($user, $course);
        $this->em()->persist($enrollment);
        $enrollment->complete($course->getLessons()[0])->recordPractice(['target' => 'VIE', 'action' => 'list_passions', 'title' => 'Chanter, Enseigner'], null, null);
        $this->em()->flush();

        $mailer = static::getContainer()->get(\App\Academie\Service\AcademyPaceMailer::class);
        $this->assertSame('lecons', $mailer->sendFor($enrollment, new \DateTimeImmutable('2026-11-23')));
        $email = $this->getMailerMessage();
        $this->assertEmailHeaderSame($email, 'Subject', 'Aujourd\'hui : votre vision');
        $this->assertEmailHtmlBodyContains($email, 'station 2 sur 2');
        $this->assertEmailHtmlBodyContains($email, 'Chanter, Enseigner');
        $this->assertEmailHtmlBodyContains($email, 'Habacuc 2:2');
        $this->assertEmailHtmlBodyContains($email, 'Commencer la séance');
        $this->assertEmailHtmlBodyContains($email, 'une séance par jour');
    }

    public function testDraftIsKeptThenResumedThenClearedWhenTheSessionEnds(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $course = $this->planCourse('list_passions');
        $url = '/academie/'.$course->getSlug().'/lecon-1';

        $crawler = $client->request('GET', $url);
        $this->assertSelectorExists('#pratique form[data-brouillon]');
        $token = $crawler->filter('#pratique input[name="_csrf_token"]')->attr('value');
        $client->request('POST', $url.'/brouillon', ['_csrf_token' => $token, 'items' => ['Chanter', '', 'Enseigner']]);
        $this->assertResponseIsSuccessful();
        $this->assertNull($this->reload($user)->getPlanDeVie()?->getPassions()->count() ?: null, 'Un brouillon n\'écrit rien dans le plan.');

        $crawler = $client->request('GET', $url);
        $this->assertSelectorExists('.academie-brouillon');
        $this->assertSame('Chanter', $crawler->filter('#pratique input[name="items[]"]')->eq(0)->attr('value'));
        $this->assertSame('Enseigner', $crawler->filter('#pratique input[name="items[]"]')->eq(2)->attr('value'));

        $client->request('POST', $url.'/brouillon', ['_csrf_token' => 'faux', 'items' => ['X']]);
        $this->assertResponseStatusCodeSame(403);

        $client->request('POST', $url.'/terminer', ['_csrf_token' => $token, 'mode' => 'plan', 'items' => ['Chanter', 'Enseigner']]);
        $enrollment = static::getContainer()->get(\App\Academie\Repository\CourseEnrollmentRepository::class)->findFor($this->reload($user), $this->em()->find(Course::class, $course->getId()));
        $this->assertNull($enrollment->draftFor($enrollment->getCourse()->getLessons()[0]), 'Brouillon effacé à la fin de la séance.');
    }

    public function testBoussoleResultOffersThePlanJourneyOnlyOncePublished(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boussole');
        $this->assertStringNotContainsString('Laissez-vous guider', (string) $client->getResponse()->getContent());

        $course = $this->planCourse('list_passions');
        $client->request('GET', '/boussole');
        $this->assertSelectorTextContains('.next-list', 'Laissez-vous guider');
        $this->assertSelectorTextContains('.next-list', $course->getTitle());
    }

    /** Séance d'un parcours de plan rattaché à un autre plan que le Plan de Vie. */
    private function sessionOf(string $plan, string $action): Course
    {
        $course = $this->makeCourse($plan, false, true, [['Séance', $plan, $action]]);
        $course->setKind(Course::KIND_PLAN);
        $this->em()->flush();

        return $course;
    }

    public function testGrowthSessionsKeepLinksStrategiesAndSiblingLists(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $vie = new PlanDeVie();
        $user->setPlanDeVie($vie);
        $this->em()->persist($vie);
        $goal = (new LifeGoal())->setPlanDeVie($vie)->setTitle('Servir les jeunes')->setCategory('GIVE')->setProgress(0);
        $this->em()->persist($goal);
        $growth = (new \App\PlanDeCroissance\Entity\PlanDeCroissance())->setUser($user);
        $user->setPlanDeCroissance($growth);
        $this->em()->persist($growth);
        $patience = (new \App\PlanDeCroissance\Entity\GrowthGoal())->setPlanDeCroissance($growth)->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::CHARACTER)
            ->setTitle('Patience')->setProgress(30)->setStrategy('Respirer avant de répondre')->setSubtype('consolidate')->addSupportedLifeGoal($goal);
        $budget = (new \App\PlanDeCroissance\Entity\GrowthGoal())->setPlanDeCroissance($growth)->setAxis(\App\PlanDeCroissance\Enum\GrowthAxis::COMPETENCE)
            ->setType('CONNAISSANCE')->setTitle('Gérer un budget')->setProgress(0);
        $this->em()->persist($patience);
        $this->em()->persist($budget);
        $this->em()->flush();
        [$patienceId, $budgetId] = [$patience->getId(), $budget->getId()];
        $client->loginUser($user = $this->reload($user));

        $course = $this->sessionOf('CROISSANCE', 'list_character_goals');
        $client->request('GET', '/academie/'.$course->getSlug().'/lecon-1');
        $this->assertSelectorTextContains('#pratique .academie-seance-deja', 'Patience');
        $this->finish($client, $course, ['items' => ['Humilité']]);
        $this->finish($client, $this->sessionOf('DEVELOPPEMENT', 'list_skills'), ['items' => ['Parler en public']]);

        $goals = $this->reload($user)->getPlanDeCroissance()->getGrowthGoals()->toArray();
        $titles = array_map(fn ($g) => $g->getTitle(), $goals);
        sort($titles);
        $this->assertSame(['Gérer un budget', 'Humilité', 'Parler en public', 'Patience'], $titles);
        $kept = $this->em()->find(\App\PlanDeCroissance\Entity\GrowthGoal::class, $patienceId);
        $this->assertSame('Respirer avant de répondre', $kept->getStrategy());
        $this->assertSame('consolidate', $kept->getSubtype());
        $this->assertSame(['Servir les jeunes'], array_map(fn ($g) => $g->getTitle(), $kept->getSupportedLifeGoals()->toArray()), 'Le lien vers l\'objectif du Plan de Vie est gardé.');
        $this->assertNotNull($this->em()->find(\App\PlanDeCroissance\Entity\GrowthGoal::class, $budgetId), 'La connaissance déjà posée reste quand on ajoute une compétence.');
    }

    public function testHygieneStewardshipAndTransmissionSessions(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $transmission = (new \App\PlanDeTransmission\Entity\PlanDeTransmission())->setUser($user)->setTreasureShift('Moins de vêtements, plus de dons');
        $user->setPlanDeTransmission($transmission);
        $this->em()->persist($transmission);
        $this->em()->persist((new \App\PlanDIntendance\Entity\StewardshipItem())->setUser($user)->setOrigin('TEMPS')->setLabel('Mes soirées'));
        $this->em()->flush();
        $client->loginUser($user = $this->reload($user));

        $this->finish($client, $this->sessionOf('HYGIENE', 'list_hygiene_domains'), ['items' => ['Le sommeil']]);
        $this->finish($client, $this->sessionOf('INTENDANCE', 'list_stewardship'), ['goals' => ['FINANCES' => ['Mon épargne'], 'TEMPS' => ['mes soirées', 'Mes matins']]]);
        $this->finish($client, $this->sessionOf('TRANSMISSION', 'write_eternity'), ['text' => 'J\'ai aimé Dieu et servi les jeunes.']);
        $this->finish($client, $this->sessionOf('TRANSMISSION', 'list_legacy_goals'), ['items' => ['L\'amour de la Parole']]);

        $user = $this->reload($user);
        $this->assertSame(['Le sommeil'], array_map(fn ($d) => $d->getName(), $user->getPlanDHygiene()->getHygieneDomains()->toArray()));
        $items = static::getContainer()->get(\App\PlanDIntendance\Repository\StewardshipItemRepository::class)->findBy(['user' => $user]);
        $labels = array_map(fn ($i) => $i->getOrigin().':'.$i->getLabel(), $items);
        sort($labels);
        $this->assertSame(['FINANCES:Mon épargne', 'TEMPS:Mes matins', 'TEMPS:Mes soirées'], $labels);
        $this->assertSame('J\'ai aimé Dieu et servi les jeunes.', $user->getPlanDeTransmission()->getEternityPhrase());
        $this->assertSame('Moins de vêtements, plus de dons', $user->getPlanDeTransmission()->getTreasureShift(), 'Le déplacement de trésor reste.');
        $this->assertSame(['L\'amour de la Parole'], array_map(fn ($l) => $l->getTitle(), $user->getPlanDeTransmission()->getLegacyGoals()->toArray()));
    }

    public function testProPlanSessionsAreClosedToFreeMembers(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $course = $this->sessionOf('HYGIENE', 'list_hygiene_domains');
        $course->setAccessLevel(Course::ACCESS_FREE);
        $this->em()->flush();

        $client->request('GET', '/academie/'.$course->getSlug().'/lecon-1');
        $this->assertSelectorTextContains('#pratique', 'KIINI SAWA PRO');
        $this->assertSelectorNotExists('#pratique button[value="plan"]');
        $this->finish($client, $course, ['items' => ['Le sommeil']]);
        $this->assertNull($this->reload($user)->getPlanDHygiene()?->getHygieneDomains()->count() ?: null);
    }

    public function testNinetyDayJourneyShowsTheSixLinesAndTheGuidedSessionsOfEachWeek(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $client->request('GET', '/parcours');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-journey-lines]', 'Rien tant qu\'aucun parcours de plan n\'est publié.');

        $course = $this->makeCourse('VIE', true, true, [['Votre But', 'VIE', 'write_purpose'], ['Vos convictions', 'VIE', 'list_convictions'], ['Vos valeurs', 'VIE', 'list_values']]);
        $course->setKind(Course::KIND_PLAN);
        $this->em()->flush();

        $client->request('GET', '/parcours');
        $this->assertSelectorTextContains('[data-journey-lines]', 'Plan de Vie');
        $this->assertSelectorTextContains('[data-journey-lines]', '0/3');
        $this->assertSelectorTextContains('#semaine-3 [data-guided-session]', 'Votre But');
        $this->assertCount(2, $client->getCrawler()->filter('#semaine-4 [data-guided-session]'), 'Semaine 4 : convictions et valeurs.');
        $this->assertSelectorNotExists('#semaine-1 [data-guided-session]');
    }
}
