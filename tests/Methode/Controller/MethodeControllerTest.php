<?php

namespace App\Tests\Methode\Controller;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MethodeControllerTest extends WebTestCase
{
    /** Visiteur : sommaire et premiers chapitres lisibles, la suite invite à créer un compte. */
    public function testVisitorReadsFirstChaptersOnly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/methode');
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/methode/introduction');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.book-body h3, .book-body p');
        $this->assertSelectorTextNotContains('article', 'La suite de ce chapitre');

        $client->request('GET', '/methode/balance-interne');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('article', 'La suite de ce chapitre');
        $this->assertSelectorExists('article a[href="/inscription"]');
    }

    public function testIndexRendersForLoggedUser(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($user);
        $client->request('GET', '/methode');

        $this->assertResponseIsSuccessful();
    }

    public function testChaptersAreReadableAndLinkedFromTheWizard(): void
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        $crawler = $client->request('GET', '/methode');
        $this->assertGreaterThanOrEqual(15, $crawler->filter('a[href^="/methode/"]')->count());

        $client->request('GET', '/methode/but-de-dieu');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'But de Dieu');
        $this->assertSelectorExists('.book-body h3');

        $client->request('GET', '/methode/chapitre-inconnu');
        $this->assertResponseStatusCodeSame(404);

        // Encart « Comprendre cette étape » dans l'assistant, relié au chapitre
        $crawler = $client->request('GET', '/plan-de-vie/assistant?step=1&single=1');
        $this->assertSelectorTextContains('.book-panel', 'Comprendre cette étape');
        $this->assertSame('/methode/but-de-dieu', $crawler->filter('.book-panel a')->attr('href'));
    }
}
