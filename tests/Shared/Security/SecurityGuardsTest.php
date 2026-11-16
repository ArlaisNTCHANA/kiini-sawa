<?php

namespace App\Tests\Shared\Security;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Garde-fous transverses : contrôle d'accès, protection CSRF par l'origine,
 * réponses JSON des API, limitation des tentatives de connexion.
 */
class SecurityGuardsTest extends WebTestCase
{
    private function loggedClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    /** Pages volontairement publiques : toute nouvelle entrée doit être justifiée. */
    private const PUBLIC_ROUTES = [
        'app_home', // page de présentation publique
        'app_health', 'app_robots', 'app_sitemap', // pages techniques publiques
        'app_login', 'app_logout',
        'app_register', 'app_forgot_password', 'app_reset_password', 'app_reset_password_form',
        'app_legal',
        'app_methode_index', 'app_methode_chapter', // premiers chapitres en libre lecture, la suite verrouillée dans la page
        'app_notification_unsubscribe', // lien signé reçu par email
        'app_organisation_join', // lien d'invitation d'une organisation
        'app_couple_join', // lien d'invitation du conjoint
    ];

    public function testEveryPageRequiresAuthenticationExceptPublicOnes(): void
    {
        $client = static::createClient();
        $routes = static::getContainer()->get('router')->getRouteCollection();
        $checked = 0;

        foreach ($routes as $name => $route) {
            if (str_starts_with($name, '_') || \in_array($name, self::PUBLIC_ROUTES, true)) {
                continue;
            }
            $methods = $route->getMethods() ?: ['GET'];
            // Paramètre remplacé par une valeur acceptée par ses contraintes (première alternative, sinon 1)
            $path = preg_replace_callback('/\{([^}]+)\}/', function ($m) use ($route) {
                $requirement = $route->getRequirement($m[1]);
                if (!$requirement) {
                    return '1';
                }
                // Première valeur simple acceptée par la contrainte
                $candidates = ['1', 'a', 'abcdef0123456789'];
                if (preg_match('/^[A-Za-z_\-]+/', $requirement, $alt)) {
                    array_unshift($candidates, $alt[0]);
                }
                foreach ($candidates as $c) {
                    if (preg_match('#^(?:'.$requirement.')$#', $c)) {
                        return $c;
                    }
                }

                return '1';
            }, $route->getPath());
            $client->request($methods[0], $path);
            $status = $client->getResponse()->getStatusCode();

            if (str_starts_with($path, '/api/')) {
                $this->assertSame(401, $status, "$name ($path) doit répondre 401 sans connexion");
            } else {
                $this->assertTrue(
                    $client->getResponse()->isRedirect('http://localhost/login') || $client->getResponse()->isRedirect('/login'),
                    "$name ($path) doit rediriger vers /login sans connexion (reçu $status)"
                );
            }
            ++$checked;
        }

        $this->assertGreaterThan(40, $checked);
    }

    public function testPublicPagesAreReachableAnonymously(): void
    {
        $client = static::createClient();
        foreach (['/', '/login', '/inscription', '/mot-de-passe/oublie', '/mot-de-passe/reinitialiser', '/mentions-legales', '/confidentialite', '/cgu', '/cgv'] as $uri) {
            $client->request('GET', $uri);
            $this->assertResponseIsSuccessful("$uri doit être accessible sans connexion");
        }
    }

    public function testCrossSitePostIsRejected(): void
    {
        $client = $this->loggedClient();
        $client->request('POST', '/api/journal/entries/create', server: [
            'HTTP_ORIGIN' => 'https://site-malveillant.example',
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['content' => 'Injection']));

        $this->assertResponseStatusCodeSame(403);
    }

    public function testCrossSiteFetchMetadataIsRejected(): void
    {
        $client = $this->loggedClient();
        $client->request('DELETE', '/api/habits/1', server: ['HTTP_SEC_FETCH_SITE' => 'cross-site']);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testSameOriginPostIsAccepted(): void
    {
        $client = $this->loggedClient();
        $client->request('POST', '/api/journal/entries/create', server: [
            'HTTP_ORIGIN' => 'http://localhost',
            'HTTP_SEC_FETCH_SITE' => 'same-origin',
            'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['content' => 'Note de test même origine']));

        $this->assertResponseIsSuccessful();
    }

    public function testEmailRoutesRefuseGet(): void
    {
        $client = $this->loggedClient();
        $client->request('GET', '/plan-de-vie/email');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testLoginIsThrottledAfterFiveFailures(): void
    {
        $client = static::createClient();
        for ($i = 0; $i < 6; ++$i) {
            $crawler = $client->request('GET', '/login');
            $client->submit($crawler->selectButton('Se connecter')->form([
                'email' => 'throttle-'.getmypid().'@kiinisawa.com',
                'password' => 'mauvais-mot-de-passe',
            ]));
            $client->followRedirect();
        }

        $this->assertSelectorTextContains('.alert', 'Trop de tentatives de connexion échouées');
    }
}
