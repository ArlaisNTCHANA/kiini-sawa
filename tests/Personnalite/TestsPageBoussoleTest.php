<?php

namespace App\Tests\Personnalite;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Point 7 (26/11/2026) : la Boussole 360 apparaît dans « Mes tests » et compte dans la progression. */
class TestsPageBoussoleTest extends WebTestCase
{
    use AccountTestTrait;

    public function testBoussoleIsListedAndCounted(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $crawler = $client->request('GET', '/mes-tests/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('[data-test-card="boussole"] a[href="/boussole"]');
        $this->assertMatchesRegularExpression('#0/\d+ tests#', $crawler->filter('.progress-bar-wrap')->text());
    }
}
