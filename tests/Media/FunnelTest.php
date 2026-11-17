<?php

namespace App\Tests\Media;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Entonnoir d'acquisition : les visites de la vitrine sont comptées (robots écartés) et l'administration les affiche.
 */
class FunnelTest extends WebTestCase
{
    use AccountTestTrait;

    private function visits(): int
    {
        return (int) static::getContainer()->get('doctrine.dbal.default_connection')->fetchOne('SELECT COALESCE(SUM(visits), 0) FROM daily_visit WHERE day = CURRENT_DATE');
    }

    public function testLandingVisitsAreCountedWithoutBots(): void
    {
        $client = static::createClient();
        $before = $this->visits();

        $client->request('GET', '/', [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (iPhone) Safari']);
        $this->assertSame($before + 1, $this->visits());

        $client->request('GET', '/', [], [], ['HTTP_USER_AGENT' => 'Googlebot/2.1']);
        $this->assertSame($before + 1, $this->visits(), 'un robot n\'est pas compté');
    }

    public function testFunnelPageIsReservedToAdmins(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/entonnoir');
        $this->assertResponseStatusCodeSame(403);
    }

    /** Les requêtes de l'entonnoir tournent (MySQL en local, MariaDB en production) et suivent les inscriptions. */
    public function testFunnelCountsSignups(): void
    {
        static::bootKernel();
        $report = static::getContainer()->get(\App\Media\Service\FunnelReport::class);
        $before = $report->funnel(7, new \DateTimeImmutable('today'))['steps'];

        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();

        $after = $report->funnel(7, new \DateTimeImmutable('today'))['steps'];
        $this->assertSame('Visites de la vitrine', $after[0]['label']);
        $this->assertSame($before[1]['count'] + 1, $after[1]['count'], 'comptes créés');
        $this->assertSame($before[3]['count'] + 1, $after[3]['count'], 'onboarding terminé');
    }

    /** La page d'administration s'affiche (un filtre Twig inexistant l'avait fait échouer au premier déploiement). */
    public function testFunnelPageRendersForAdmin(): void
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(\App\Compte\Repository\UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        foreach ([7, 30, 90] as $days) {
            $client->request('GET', '/admin/entonnoir?jours='.$days);
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('body', 'Visites de la vitrine');
            $this->assertSelectorTextContains('body', 'Onboarding terminé');
        }
    }
}
