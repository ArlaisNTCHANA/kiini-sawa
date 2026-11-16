<?php

namespace App\Tests\Shared;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Préparation de la mise en ligne : pages techniques publiques, aucune ressource externe,
 * en-têtes de sécurité, contrôle avant lancement.
 */
class LaunchReadinessTest extends WebTestCase
{
    public function testHealthRobotsAndSitemap(): void
    {
        $client = static::createClient();
        $client->request('GET', '/sante');
        $this->assertResponseIsSuccessful();
        $this->assertSame('ok', json_decode($client->getResponse()->getContent(), true)['status']);

        $client->request('GET', '/robots.txt');
        $this->assertStringContainsString('Disallow: /', $client->getResponse()->getContent());
        $this->assertStringContainsString('Sitemap:', $client->getResponse()->getContent());

        $client->request('GET', '/sitemap.xml');
        $this->assertStringContainsString('/inscription', $client->getResponse()->getContent());
    }

    public function testNoThirdPartyResourcesOnPublicPages(): void
    {
        $client = static::createClient();
        foreach (['/', '/login', '/inscription', '/cgu'] as $url) {
            $client->request('GET', $url);
            $html = $client->getResponse()->getContent();
            foreach (['fonts.googleapis', 'fonts.gstatic', 'cdnjs.cloudflare', 'cdn.jsdelivr', 'unpkg.com'] as $host) {
                $this->assertStringNotContainsString($host, $html, "$url ne doit charger aucune ressource de $host (RGPD)");
            }
        }
        $csp = $client->getResponse()->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('googleapis', $csp);
    }

    public function testHstsOnlyOverHttps(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        $this->assertFalse($client->getResponse()->headers->has('Strict-Transport-Security'));
        $client->request('GET', '/login', server: ['HTTPS' => 'on']);
        $this->assertResponseHeaderSame('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function testLaunchCheckReportsBlockingItems(): void
    {
        $kernel = static::bootKernel();
        $tester = new CommandTester((new Application($kernel))->find('app:launch:check'));
        $code = $tester->execute([]);
        $this->assertSame(1, $code, 'En environnement de test, des points bloquants sont attendus');
        $this->assertStringContainsString('Mode production', $tester->getDisplay());
        $this->assertStringContainsString('[À COMPLÉTER]', $tester->getDisplay());
    }
}
