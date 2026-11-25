<?php

namespace App\Tests\PlanDeVie\Controller;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Contre-audit visuel du 25/11/2026 : les pages des plans (Croissance, Développement, Hygiène, Transmission)
 * affichaient du code brut en haut de page, car le plan initial fermait l'attribut x-init par ses guillemets.
 */
class PlanPagesOpenOnTheirPlanTest extends WebTestCase
{
    /** @return iterable<array{string, string}> */
    public static function plans(): iterable
    {
        yield ['/plan-de-croissance', 'CROISSANCE'];
        yield ['/plan-de-developpement', 'DEVELOPPEMENT'];
        yield ['/plan-d-hygiene', 'HYGIENE'];
        yield ['/plan-de-transmission', 'TRANSMISSION'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('plans')]
    public function testPlanPageOpensOnItsPlanWithoutRawCode(string $url, string $plan): void
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $crawler = $client->request('GET', $url);

        $this->assertResponseIsSuccessful();
        $wrap = $crawler->filter('.strat-wrap');
        $this->assertCount(1, $wrap);
        $this->assertStringContainsString("'".$plan."'", (string) $wrap->attr('x-init'));
        $this->assertStringNotContainsString('$nextTick', $crawler->filter('main')->count() ? $crawler->filter('main')->text() : $crawler->filter('body')->text());
    }
}
