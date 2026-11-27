<?php

namespace App\Tests\Admin;

use App\Boussole\Service\BoussoleEngine;
use App\Boussole\Service\BoussoleRecorder;
use App\Compte\Repository\UserRepository;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Admin › Les chiffres › La Boussole 360 (27/11/2026) : un visiteur qui passe la Boussole est compté (sans donnée
 * personnelle), et compté encore s'il la garde en créant son compte ; la page ne montre que des totaux.
 */
class BoussoleStatsTest extends WebTestCase
{
    use AccountTestTrait;

    private function today(string $column): int
    {
        return (int) $this->em()->getConnection()->fetchOne('SELECT COALESCE(SUM('.$column.'), 0) FROM boussole_daily WHERE day = CURRENT_DATE');
    }

    public function testVisitorPassesAndNewMembersAreCountedAnonymously(): void
    {
        $client = static::createClient();
        $recorder = static::getContainer()->get(BoussoleRecorder::class);
        $answers = array_fill(0, 24, 3);
        $visitorsBefore = $this->today('visitors');
        $newBefore = $this->today('new_members');

        $session = new Session(new MockArraySessionStorage());
        $recorder->keepPending($session, $answers);
        $this->assertSame($visitorsBefore + 1, $this->today('visitors'));

        $member = $this->createUser();
        $this->assertNotNull($recorder->flushPending($session, $member));
        $this->assertSame($newBefore + 1, $this->today('new_members'), 'Gardée par un compte tout neuf');

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/boussole?jours=7');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'passations sans compte');
        $this->assertSelectorTextContains('main', 'Ce que disent les passations');
        $this->assertStringNotContainsString((string) $member->getEmail(), (string) $client->getResponse()->getContent());
        $this->assertCount(\count(BoussoleEngine::PLAN_ORDER), $client->getCrawler()->filter('main table tbody tr'));
    }

    public function testMembersCannotSeeTheFigures(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/boussole');
        $this->assertResponseStatusCodeSame(403);
    }
}
