<?php

namespace App\Tests\Personnalite;

use App\Notification\Entity\Notification;
use App\Notification\Service\ReminderService;
use App\Personnalite\Entity\TestAction;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\Rituels\Entity\Habit;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Mon profil KIINI SAWA » : synthèse croisée, réservée PRO au-delà de la carte d'identité ; actions des rapports
 * ajoutées aux rituels ou au Plan de Croissance ; rappel à 30 jours.
 */
class SynthesisTest extends WebTestCase
{
    use AccountTestTrait;

    private function takeTests($user): void
    {
        $service = static::getContainer()->get(AssessmentService::class);
        $disc = [];
        foreach (Instruments::items('disc') as $item) {
            $disc[$item['key']] = 'SD';
        }
        $service->record($user, 'disc', $disc, 400);
        $gifts = [];
        foreach (Instruments::items('dons-spirituels') as $i => $item) {
            $gifts[$item['key']] = 1 + ($i % 5);
        }
        $service->record($user, 'dons-spirituels', $gifts, 400);
        $enn = [];
        foreach (Instruments::items('enneagramme') as $i => $item) {
            $enn[$item['key']] = 1 + ($i * 7 % 5);
        }
        $service->record($user, 'enneagramme', $enn, 400);
    }

    public function testNeedsTwoTests(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $client->request('GET', '/mes-tests/synthese');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Encore 2 tests');
    }

    public function testProSynthesisWithVocationAndPdf(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $this->takeTests($user);
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/synthese');
        $this->assertResponseIsSuccessful();
        foreach (['Votre carte d\'identité', 'Votre zone de vocation', 'Vos ressources et vos fragilités', 'Vos trois priorités de croissance', 'Bon usage de ce rapport', 'Une vocation se discerne'] as $t) {
            $this->assertSelectorTextContains('main', $t);
        }
        $client->request('GET', '/mes-tests/synthese/pdf');
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }

    public function testFreeUsersSeeIdentityOnly(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->takeTests($user);
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/synthese');
        $this->assertSelectorTextContains('main', 'Votre carte d\'identité');
        $this->assertSelectorTextContains('main', 'réservée à l\'offre PRO');
        $this->assertSelectorTextNotContains('main', 'Vos trois priorités de croissance');
    }

    public function testAddActionsThenFollowUpAfter30Days(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $this->takeTests($user);
        $client->loginUser($user);
        $crawler = $client->request('GET', '/mes-tests/synthese');
        $client->submit($crawler->filter('#priorites form')->first()->form());
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Dans vos rituels');
        $crawler = $client->getCrawler();
        $client->submit($crawler->filter('#priorites form button:contains("Plan de Croissance")')->first()->form());

        $em = $this->em();
        $this->assertSame(1, $em->getRepository(Habit::class)->count(['user' => $user->getId()]));
        $em->clear();
        $plan = $em->getRepository(\App\Compte\Entity\User::class)->find($user->getId())->getPlanDeCroissance();
        $this->assertNotNull($plan, 'Plan de Croissance créé au besoin');
        $this->assertSame(1, $em->getRepository(GrowthGoal::class)->count(['sourceType' => 'TEST', 'planDeCroissance' => $plan]));
        $actions = $em->getRepository(TestAction::class)->findBy(['user' => $user->getId()]);
        $this->assertCount(2, $actions);

        $reminders = static::getContainer()->get(ReminderService::class);
        $u = $em->getRepository(\App\Compte\Entity\User::class)->find($user->getId());
        $this->assertSame(0, $reminders->testFollowUps($u, new \DateTimeImmutable()), 'Pas de rappel avant 30 jours');
        $this->assertSame(2, $reminders->testFollowUps($u, new \DateTimeImmutable('+31 days')));
        $this->assertSame(0, $reminders->testFollowUps($u, new \DateTimeImmutable('+32 days')), 'Un seul rappel par action');
        $this->assertGreaterThan(0, $em->getRepository(Notification::class)->count(['user' => $user->getId(), 'title' => 'Il y a un mois, vous avez choisi une action']));
    }
}
