<?php

namespace App\Tests\Personnalite;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Catalogue des tests : pas de test trop court, une mention de fiabilité par test, anciennes adresses redirigées.
 */
class TestsCatalogTest extends WebTestCase
{
    use AccountTestTrait;

    public function testCatalogShowsOnlyReliableTestsWithOneLabelEach(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/mes-tests/');
        $this->assertResponseIsSuccessful();

        $html = $client->getResponse()->getContent();
        foreach (['Intelligences multiples', 'Architecture des talents', 'indicatifs', 'Validé scientifiquement'] as $gone) {
            $this->assertStringNotContainsStringIgnoringCase($gone, $html);
        }
        $this->assertSelectorTextContains('main', 'Que valent ces tests ?');
        $this->assertSelectorTextContains('main', 'Questionnaire scientifique');
        $this->assertSelectorTextContains('main', 'Auto-évaluation');
        $this->assertSelectorTextContains('main', 'Faire le point');
    }

    public function testRemovedAndReplacedTestsRedirect(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        foreach (['/mes-tests/intelligences/passer', '/mes-tests/talent/passer', '/mes-tests/talent/resultats'] as $url) {
            $client->request('GET', $url);
            $this->assertResponseRedirects('/mes-tests/', 301, $url);
        }

        // Ancien lien « résultats » d'un test remplacé, sans résultat : on propose de passer le questionnaire
        $client->request('GET', '/mes-tests/mbti/resultats');
        $this->assertResponseRedirects('/mes-tests/questionnaire/type');
        $client->request('GET', '/mes-tests/dons/passer');
        $this->assertResponseRedirects('/mes-tests/questionnaire/dons-spirituels', 301);
    }
}
