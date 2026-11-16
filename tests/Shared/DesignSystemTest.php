<?php

namespace App\Tests\Shared;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Système visuel (docs/design.md) : chaque page principale a l'en-tête commun, et un seul titre de page.
 */
class DesignSystemTest extends WebTestCase
{
    use AccountTestTrait;

    public function testMainPagesUseTheSharedHeader(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);

        foreach (['/mes-plans/rituels', '/journal', '/planner', '/bilan', '/social', '/mes-tests/', '/profil', '/abonnement',
            '/notifications', '/methode', '/couple', '/organisation', '/plan-de-vie/historique', '/bilan/revue-hebdomadaire', '/bilan/revue-mensuelle'] as $url) {
            $crawler = $client->request('GET', $url);
            $this->assertResponseIsSuccessful($url);
            $this->assertCount(1, $crawler->filter('main h1'), $url.' : un seul titre de page');
            $this->assertCount(1, $crawler->filter('main h1.ks-header__title'), $url.' : en-tête commun');
        }
    }
}
