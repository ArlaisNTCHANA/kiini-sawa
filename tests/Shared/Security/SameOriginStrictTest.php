<?php

namespace App\Tests\Shared\Security;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Audit de sécurité, lot 4 : le contrôle d'origine est « fail-closed » dès qu'un cookie de session accompagne la requête
 * (la suite de tests le coupe par défaut : APP_SAME_ORIGIN_STRICT=0 dans phpunit.dist.xml ; ici on le rallume).
 */
class SameOriginStrictTest extends WebTestCase
{
    use AccountTestTrait;

    protected function setUp(): void
    {
        $_SERVER['APP_SAME_ORIGIN_STRICT'] = $_ENV['APP_SAME_ORIGIN_STRICT'] = '1';
        putenv('APP_SAME_ORIGIN_STRICT=1');
    }

    protected function tearDown(): void
    {
        $_SERVER['APP_SAME_ORIGIN_STRICT'] = $_ENV['APP_SAME_ORIGIN_STRICT'] = '0';
        putenv('APP_SAME_ORIGIN_STRICT=0');
        parent::tearDown();
    }

    private function post(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, array $server = []): int
    {
        $client->request('POST', '/api/rapide/sync', server: $server + ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{"ops":[]}');

        return $client->getResponse()->getStatusCode();
    }

    public function testASessionCookieWithoutAnyOriginHeaderIsRefused(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $this->assertSame(403, $this->post($client), 'cookie de session sans Origin, Sec-Fetch-Site ni Referer : refusé');
        $this->assertStringContainsString('ne provient pas de KIINI SAWA', $client->getResponse()->getContent());
    }

    public function testBrowserRequestsFromTheSiteItselfStillWork(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $this->assertSame(200, $this->post($client, ['HTTP_ORIGIN' => 'http://localhost']));
        $this->assertSame(200, $this->post($client, ['HTTP_SEC_FETCH_SITE' => 'same-origin']));
        $this->assertSame(200, $this->post($client, ['HTTP_REFERER' => 'http://localhost/dashboard']));
    }

    public function testCrossSiteRequestsAreRefusedWhateverTheMode(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $this->assertSame(403, $this->post($client, ['HTTP_ORIGIN' => 'https://evil.example']));
        $this->assertSame(403, $this->post($client, ['HTTP_SEC_FETCH_SITE' => 'cross-site']));
        $this->assertSame(403, $this->post($client, ['HTTP_REFERER' => 'https://evil.example/page']));
    }

    public function testRequestsWithoutSessionAreNotAffected(): void
    {
        $client = static::createClient();
        // Ni cookie de session ni en-tête d'origine (outil, application mobile à jeton) : la requête n'a aucun droit à voler
        $this->assertSame(401, $this->post($client), 'passe le contrôle d\'origine, puis refusée faute de connexion');
    }

    public function testTheRememberMeCookieCountsAsASession(): void
    {
        $client = static::createClient();
        $client->getCookieJar()->set(new \Symfony\Component\BrowserKit\Cookie('REMEMBERME', 'valeur', null, '/', 'localhost'));
        $this->assertSame(403, $this->post($client));
    }

    public function testStrictModeIsOnByDefaultAndOffInTheMainSuite(): void
    {
        $this->assertSame('1', (string) static::getContainer()->getParameter('env(APP_SAME_ORIGIN_STRICT)'), 'activé par défaut hors tests');
        $this->assertStringContainsString('APP_SAME_ORIGIN_STRICT" value="0"', (string) file_get_contents(\dirname(__DIR__, 3).'/phpunit.dist.xml'));
    }
}
