<?php

namespace App\Tests\PlanDeVie;

use App\Compte\Entity\User;
use App\PlanDeVie\Service\PlanProgress;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cercle des plans vivant (Mes plans) : progression par plan et prochaine étape.
 */
class PlanProgressTest extends WebTestCase
{
    use AccountTestTrait;

    private function progress(User $user): array
    {
        $this->em()->clear();

        return static::getContainer()->get(PlanProgress::class)->forUser($this->em()->find(User::class, $user->getId()));
    }

    public function testNewUserStartsAtZeroWithAFirstStep(): void
    {
        $p = $this->progress($this->createUser(pro: true));
        $this->assertSame(['VIE', 'CROISSANCE', 'DEVELOPPEMENT', 'HYGIENE', 'INTENDANCE', 'TRANSMISSION'], array_keys($p));
        foreach ($p as $key => $plan) {
            $this->assertSame(0, $plan['percent'], $key);
            $this->assertNotNull($plan['next'], $key);
        }
        $this->assertSame('Écrire mon But de Dieu', $p['VIE']['next']['label']);
    }

    public function testStepsAdvanceAndNextStepMoves(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);

        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', ['purpose_statement' => 'Je suis créé pour former afin d\'apporter la lumière chez les jeunes.']);
        $client->request('POST', '/plan-d-intendance/element', ['origin' => 'TEMPS', 'label' => 'Mes soirées']);
        $client->request('POST', '/plan-d-intendance/rythme', ['frequency' => 'MONTHLY', 'review' => '1']);

        $p = $this->progress($user);
        $this->assertSame(25, $p['VIE']['percent']);
        $this->assertSame('Nommer au moins trois valeurs', $p['VIE']['next']['label']);
        $this->assertSame(25, $p['INTENDANCE']['percent']); // seul le compte rendu est fait ; l'inventaire n'a qu'un élément
        $this->assertTrue($p['INTENDANCE']['steps'][3]['done']);
    }

    public function testCircleIsOnThePlansPage(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $crawler = $client->request('GET', '/mes-plans');
        $this->assertResponseIsSuccessful();
        $this->assertCount(6, $crawler->filter('[data-plans-circle] .pc__node'));
        $this->assertCount(6, $crawler->filter('[data-plans-circle] [data-plan-info]'));
        $this->assertSelectorTextContains('[data-plan-info="INTENDANCE"]', 'Dresser mon inventaire');
        $this->assertSame('/plan-d-intendance', $crawler->filter('.pc__node[data-plan="INTENDANCE"]')->attr('href'));
    }

    public function testLockedPlansPointToSubscription(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $crawler = $client->request('GET', '/mes-plans');
        $this->assertStringContainsString('/abonnement', $crawler->filter('.pc__node[data-plan="CROISSANCE"]')->attr('href'));
        $this->assertSame('/plan-de-vie', $crawler->filter('.pc__node[data-plan="VIE"]')->attr('href'));
    }
}
