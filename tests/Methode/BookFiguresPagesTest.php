<?php

namespace App\Tests\Methode;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Les schémas interactifs apparaissent dans l'assistant du Plan de Vie (étapes 1, 2, 10, 11, 12, 13). */
class BookFiguresPagesTest extends WebTestCase
{
    use AccountTestTrait;

    public function testWizardStepsShowTheInteractiveFigures(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $expected = [1 => 3, 2 => 3, 10 => 5, 11 => 3, 12 => 3, 13 => 3];
        foreach ($expected as $step => $nodes) {
            $crawler = $client->request('GET', '/plan-de-vie/assistant?step='.$step.'&single=1');
            $this->assertResponseIsSuccessful('étape '.$step);
            $this->assertSame($nodes, $crawler->filter('[data-cycle-node]')->count(), 'étape '.$step);
            $this->assertGreaterThanOrEqual(1, $crawler->filter('[data-book-figure]')->count(), 'étape '.$step.' : l\'image du livre reste disponible');
        }
    }
}
