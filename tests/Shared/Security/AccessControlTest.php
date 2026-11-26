<?php

namespace App\Tests\Shared\Security;

use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Psychometrie\DiscCharts;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/** Audit de sécurité, lot 4 : droits PRO, documentation, déconnexion, méthodes HTTP, hôtes de confiance, hygiène. */
class AccessControlTest extends WebTestCase
{
    use AccountTestTrait;

    private const TOKEN = ['_csrf_token' => 'jeton-de-test'];

    // ── Droits PRO par les adresses directes ─────────────────────────────────

    public function testGrowthAxisAndPrinciplesAreProOnlyForAFreeUser(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('POST', '/plan-de-croissance/axe/CONVICTION/1', self::TOKEN + ['strategy' => 'x']);
        $this->assertResponseRedirects('/abonnement?fonction=croissance');

        foreach (['CROISSANCE' => 'croissance', 'DEVELOPPEMENT' => 'developpement', 'TRANSMISSION' => 'transmission', 'HYGIENE' => 'hygiene'] as $pillar => $feature) {
            $client->request('POST', '/principes/'.$pillar, self::TOKEN + ['custom_principle_titles' => ['Intégrité']]);
            $this->assertResponseRedirects('/abonnement?fonction='.$feature, null, $pillar);
        }
    }

    public function testProUsersKeepAccessToThoseSameAddresses(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);
        $client->request('POST', '/principes/CROISSANCE', self::TOKEN + ['custom_principle_titles' => ['Intégrité'], 'custom_principle_descriptions' => ['x']]);
        $this->assertResponseRedirects();
        $this->assertStringNotContainsString('/abonnement', $client->getResponse()->headers->get('Location'));
        $this->assertSame(1, $this->em()->getRepository(\App\PlanDeCroissance\Entity\Principle::class)->count(['user' => $user->getId()]));
    }

    public function testTheFreeHabitLimitIsExactlyThree(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $em = $this->em();
        for ($i = 1; $i <= 3; ++$i) {
            $em->persist((new Habit())->setUser($user)->setTitle('Rituel '.$i)->setFrequency(HabitFrequency::DAILY));
        }
        $em->flush();
        $em->clear();
        $client->loginUser($em->find(User::class, $user->getId()));

        $form = $client->request('GET', '/mes-plans/rituels')->filter('form[name="habit"]')->form();
        $form['habit[title]'] = 'Le quatrième';
        $client->submit($form);
        $this->assertResponseRedirects('/abonnement?limite=habits', null, 'trois rituels existent déjà : le quatrième est refusé');
        $this->assertSame(3, $this->em()->getRepository(Habit::class)->count(['user' => $user->getId()]));
    }

    public function testTheThirdFreeHabitIsStillAccepted(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $em = $this->em();
        for ($i = 1; $i <= 2; ++$i) {
            $em->persist((new Habit())->setUser($user)->setTitle('Rituel '.$i)->setFrequency(HabitFrequency::DAILY));
        }
        $em->flush();
        $em->clear();
        $client->loginUser($em->find(User::class, $user->getId()));
        $form = $client->request('GET', '/mes-plans/rituels')->filter('form[name="habit"]')->form();
        $form['habit[title]'] = 'Le troisième';
        $client->submit($form);
        // Point 7 : l'enracinement du nouveau rituel s'ouvre (?relier=<id>)
        $this->assertStringStartsWith('/mes-plans/rituels?relier=', (string) $client->getResponse()->headers->get('Location'));
        $this->assertSame(3, $this->em()->getRepository(Habit::class)->count(['user' => $user->getId()]));
    }

    public function testSelfAssessmentPdfIsProOnly(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/mes-tests/equilibre/resultats/pdf');
        $this->assertResponseRedirects('/abonnement?fonction=test_pdf');
    }

    // ── Documentation d'administration ──────────────────────────────────────

    public function testDocumentationIsForAdministratorsOnly(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/doc');
        $this->assertResponseRedirects('/login');

        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/doc');
        $this->assertResponseStatusCodeSame(403);

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/doc');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/admin/doc/modules/rapide');
        $this->assertResponseIsSuccessful();
    }

    public function testRawHtmlInDocumentationIsNeutralised(): void
    {
        $file = \dirname(__DIR__, 3).'/docs/zz-test-securite.md';
        file_put_contents($file, "# Test\n\n<script>alert('xss')</script>\n\n<img src=x onerror=alert(1)>\n\n[lien](javascript:alert(1))\n");
        try {
            $client = static::createClient();
            $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
            $client->request('GET', '/admin/doc/zz-test-securite');
            $this->assertResponseIsSuccessful();
            $html = $client->getResponse()->getContent();
            $this->assertStringNotContainsString("<script>alert('xss')</script>", $html);
            $this->assertStringNotContainsString('<img src=x', $html);
            $this->assertStringNotContainsString('href="javascript:', $html);
            $this->assertStringContainsString('&lt;script&gt;', $html);
        } finally {
            unlink($file);
        }
    }

    public function testBookMarkdownIsParsedInSafeMode(): void
    {
        $src = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Methode/Service/BookLibrary.php');
        $this->assertStringContainsString('setSafeMode(true)', $src);
        $this->assertStringContainsString('setSafeMode(true)', (string) file_get_contents(\dirname(__DIR__, 3).'/src/Admin/Controller/DocController.php'));
    }

    // ── Graphiques : texte modifiable échappé ───────────────────────────────

    public function testDiscChartEscapesTheEditableDimensionNames(): void
    {
        static::createClient();
        Content::setLoader(fn () => ['disc.dimensions.D.name' => '<script>alert(1)</script> & "x"']);
        try {
            $svg = DiscCharts::compass(['D' => 50, 'I' => 50, 'S' => 50, 'C' => 50], ['D' => 50, 'I' => 50, 'S' => 50, 'C' => 50]);
            $this->assertStringNotContainsString('<script>', $svg);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;x&quot;', $svg);
            $this->assertNotFalse(simplexml_load_string($svg), 'le SVG reste du XML valide');
        } finally {
            Content::setLoader(null);
        }
    }

    // ── Déconnexion par formulaire POST ─────────────────────────────────────

    private function stillLoggedIn(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): bool
    {
        $client->request('GET', '/dashboard');

        return '/login' !== $client->getResponse()->headers->get('Location');
    }

    public function testLogoutNeedsAPostWithAValidToken(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/logout');
        $this->assertResponseStatusCodeSame(405, 'un lien (GET) ne déconnecte pas');
        $this->assertTrue($this->stillLoggedIn($client));

        $client->request('POST', '/logout', ['_csrf_token' => 'faux']);
        $this->assertTrue($this->stillLoggedIn($client), 'mauvais jeton : toujours connecté');
        $client->request('POST', '/logout');
        $this->assertTrue($this->stillLoggedIn($client), 'sans jeton : toujours connecté');

        $client->request('POST', '/logout', self::TOKEN);
        $this->assertResponseRedirects();
        $this->assertFalse($this->stillLoggedIn($client), 'avec le jeton : déconnecté');
    }

    public function testLogoutIsAFormEverywhereInTheTemplates(): void
    {
        $root = \dirname(__DIR__, 3).'/templates';
        $links = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!str_ends_with($f->getFilename(), '.twig')) {
                continue;
            }
            $src = (string) file_get_contents($f->getPathname());
            if (preg_match('/<a[^>]+href="\{\{ path\(\'app_logout\'\) \}\}"/', $src)) {
                $links[] = $f->getFilename();
            }
        }
        $this->assertSame([], $links, 'plus aucun lien de déconnexion : des formulaires POST avec jeton');
        $base = (string) file_get_contents($root.'/base.html.twig');
        $this->assertSame(2, preg_match_all('/<form method="post" action="\{\{ path\(\'app_logout\'\) \}\}"/', $base));
        $this->assertSame(2, preg_match_all("/csrf_token\\('logout_form'\\)/", $base));
    }

    public function testTheShellOfEveryPageOffersALogoutForm(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(2, $crawler->filter('form[action="/logout"] input[name="_csrf_token"]')->count());
    }

    // ── Méthodes HTTP réelles ───────────────────────────────────────────────

    public function testNoRouteAcceptsEveryHttpMethod(): void
    {
        static::createClient();
        $open = [];
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if ([] === $route->getMethods() && !str_starts_with($name, '_') && 'ux_live_component' !== $name) {
                $open[] = $name;
            }
        }
        $this->assertSame([], $open, 'routes sans restriction de méthode');
    }

    public function testFormerlyOpenRoutesRefuseOtherMethods(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        foreach (['/dashboard', '/journal', '/bilan', '/social', '/planner', '/mes-tests/'] as $url) {
            foreach (['POST', 'PUT', 'DELETE'] as $method) {
                $client->request($method, $url);
                $this->assertResponseStatusCodeSame(405, $method.' '.$url);
            }
        }
    }

    // ── Hôtes de confiance ──────────────────────────────────────────────────

    public function testAForgedHostHeaderIsRefused(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login', server: ['HTTP_HOST' => 'evil.example']);
        $this->assertResponseStatusCodeSame(400);
        $client->request('GET', '/login', server: ['HTTP_HOST' => 'kiinisawa.com.evil.example']);
        $this->assertResponseStatusCodeSame(400);
        foreach (['localhost', 'kiinisawa.com', 'www.kiinisawa.com'] as $host) {
            $client->request('GET', '/login', server: ['HTTP_HOST' => $host]);
            $this->assertResponseIsSuccessful($host);
        }
    }

    public function testTrustedHostsHasASafeProductionDefault(): void
    {
        $env = (string) file_get_contents(\dirname(__DIR__, 3).'/.env');
        $this->assertMatchesRegularExpression('/^TRUSTED_HOSTS=.*kiinisawa\\\\\.com/m', $env);
        $this->assertStringContainsString("trusted_hosts: '%env(TRUSTED_HOSTS)%'", (string) file_get_contents(\dirname(__DIR__, 3).'/config/packages/framework.yaml'));
    }

    // ── Dépendances ─────────────────────────────────────────────────────────

    public function testTheUnusedApiDocBundleIsGone(): void
    {
        $root = \dirname(__DIR__, 3);
        $this->assertStringNotContainsString('nelmio', (string) file_get_contents($root.'/composer.json'));
        $this->assertStringNotContainsString('nelmio/api-doc-bundle', (string) file_get_contents($root.'/composer.lock'));
        $this->assertDirectoryDoesNotExist($root.'/vendor/nelmio/api-doc-bundle');
    }
}
