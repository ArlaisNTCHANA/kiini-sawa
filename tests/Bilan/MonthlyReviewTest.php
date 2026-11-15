<?php

namespace App\Tests\Bilan;

use App\Bilan\Controller\MonthlyReviewController;
use App\PlanDeVie\Entity\PlanHistory;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Revue mensuelle et historique daté des plans.
 */
class MonthlyReviewTest extends WebTestCase
{
    use AccountTestTrait;

    public function testReviewedMonthRule(): void
    {
        $this->assertSame('2026-10-01', MonthlyReviewController::reviewedMonth(new \DateTimeImmutable('2026-11-03'))->format('Y-m-d'));
        $this->assertSame('2026-11-01', MonthlyReviewController::reviewedMonth(new \DateTimeImmutable('2026-11-15'))->format('Y-m-d'));
    }

    public function testMonthlyReviewSavesAndSnapshotsPlans(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);
        $client->request('POST', '/plan-de-vie/assistant?step=1&single=1', ['purpose_statement' => 'Bâtir des ponts entre les générations.']);

        $crawler = $client->request('GET', '/bilan/revue-mensuelle');
        $this->assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Enregistrer ma revue du mois')->form(), ['progress' => 'Rituels du matin tenus', 'decision' => 'Je dis non aux réunions du soir']);
        $this->assertResponseRedirects('/bilan/revue-mensuelle');

        // Un seul enregistrement par mois, complété au second envoi
        $crawler = $client->request('GET', '/bilan/revue-mensuelle');
        $client->submit($crawler->selectButton('Enregistrer ma revue du mois')->form(), ['decision' => 'Je dis non aux réunions du soir, sauf urgence']);
        $this->assertSame(1, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM monthly_review WHERE user_id = ?', [$user->getId()]));

        // Une version datée des plans a été créée
        $versions = $this->em()->getRepository(PlanHistory::class)->findBy(['user' => $user->getId()]);
        $this->assertCount(1, $versions);
        $this->assertStringContainsString('Bâtir des ponts', $versions[0]->getData());

        $client->request('GET', '/plan-de-vie/historique/'.$versions[0]->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Bâtir des ponts entre les générations.');

        // Un autre utilisateur ne peut pas la lire
        $client->loginUser($this->createUser(pro: true));
        $client->request('GET', '/plan-de-vie/historique/'.$versions[0]->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testManualVersion(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);
        $crawler = $client->request('GET', '/plan-de-vie/historique');
        $client->submit($crawler->selectButton('Enregistrer une version')->form(), ['title' => 'Avant ma reconversion']);
        $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Avant ma reconversion');
    }
}
