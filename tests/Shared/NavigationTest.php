<?php

namespace App\Tests\Shared;

use App\Shared\Twig\NavigationExtension;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Navigation par rubriques : chaque page du menu s'ouvre, la rubrique active est mise en avant, et le menu du compte
 * ne contient plus que le compte.
 */
class NavigationTest extends WebTestCase
{
    use AccountTestTrait;

    public function testEveryMenuPageOpensAndHighlightsItsSection(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $router = static::getContainer()->get('router');
        foreach (NavigationExtension::SECTIONS as $key => $section) {
            $routes = $section['items'] ? array_column($section['items'], 1) : [$section['route']];
            foreach ($routes as $route) {
                $client->request('GET', $router->generate($route));
                $code = $client->getResponse()->getStatusCode();
                if (302 === $code) {
                    $client->followRedirect();
                    $code = $client->getResponse()->getStatusCode();
                }
                $this->assertSame(200, $code, $route);
                if ('app_dashboard' === $route) {
                    continue;
                }
                $active = $client->getCrawler()->filter('#ks-top-nav .topnav-item.active');
                if ($active->count()) {
                    $this->assertStringContainsString($section['label'], $active->text(), $route.' met en avant sa rubrique');
                }
            }
        }
    }

    /** Le contenu des plans suit la décision du 15/11/2026 : la compétence relève du Développement, plus de la Croissance. */
    public function testPlanDescriptionsComeFromTheCatalog(): void
    {
        $catalog = \App\PlanDeVie\Service\PlanCatalog::PLANS;
        $this->assertStringNotContainsStringIgnoringCase('compétence', $catalog['CROISSANCE']['desc']);
        $this->assertStringContainsStringIgnoringCase('compétence', $catalog['DEVELOPPEMENT']['desc']);
        $byRoute = array_column($catalog, null, 'route');
        $menuOrder = [];
        foreach (NavigationExtension::SECTIONS['plans']['items'] as $item) {
            if (isset($byRoute[$item[1]])) {
                $plan = $byRoute[$item[1]];
                $this->assertSame($plan['question'].'. '.$plan['desc'], $item[3], $item[0].' : question et contenu du catalogue');
                $menuOrder[] = $item[1];
            }
        }
        // Ordre du livre : Vie, Croissance, Développement personnel, Hygiène de vie, Intendance, Transmission
        $this->assertSame(array_column($catalog, 'route'), $menuOrder);
        $this->assertSame(['VIE', 'CROISSANCE', 'DEVELOPPEMENT', 'HYGIENE', 'INTENDANCE', 'TRANSMISSION'], array_keys($catalog));
    }

    public function testHeaderShowsSixSectionsAndLeanAccountMenu(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $crawler = $client->request('GET', '/mes-tests/');
        $top = $crawler->filter('#ks-top-nav .topnav-links')->text();
        foreach (['Aujourd\'hui', 'Me connaître', 'Mes plans', 'Mon quotidien', 'Ensemble', 'La Méthode'] as $label) {
            $this->assertStringContainsString($label, $top);
        }
        $this->assertStringContainsString('Me connaître', $crawler->filter('#ks-top-nav .topnav-item.active')->text());
        $account = $crawler->filter('.topnav-user-dropdown')->text();
        $this->assertStringContainsString('Mon profil', $account);
        $this->assertStringNotContainsString('Mes tests', $account, 'Les fonctionnalités ne sont plus dans le menu du compte');
        $this->assertSelectorTextContains('.ks-sheet', 'Ensemble');
    }

    /** « Mes plans » : l'ancienne adresse redirige, et la page des rituels reste dans « Mon quotidien ». */
    public function testPlansOverviewAddress(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/mes-strategies');
        $this->assertResponseRedirects('/mes-plans', 301);

        $client->request('GET', '/mes-plans');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes plans');

        // Le préfixe le plus long l'emporte : /mes-plans/rituels appartient à « Mon quotidien », pas à « Mes plans »
        foreach (['/mes-plans' => 'plans', '/mes-plans/rituels' => 'daily', '/plan-d-intendance' => 'plans', '/bilan/saison' => 'daily'] as $path => $section) {
            $stack = new \Symfony\Component\HttpFoundation\RequestStack();
            $stack->push(\Symfony\Component\HttpFoundation\Request::create($path));
            $this->assertSame($section, (new NavigationExtension($stack))->activeSection(), $path);
        }
    }

    /** Une icône écrite dans une variable Twig doit être rendue, pas affichée en texte (« <i data-lucide=… »). */
    public function testNoIconMarkupIsDisplayedAsText(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $router = static::getContainer()->get('router');
        $routes = ['app_dashboard', 'app_couple', 'app_organisation_index', 'app_journal', 'app_plans'];
        foreach (NavigationExtension::SECTIONS as $section) {
            $routes = array_merge($routes, $section['items'] ? array_column($section['items'], 1) : [$section['route']]);
        }
        foreach (array_unique($routes) as $route) {
            try {
                $client->request('GET', $router->generate($route));
            } catch (\Symfony\Component\Routing\Exception\RouteNotFoundException) {
                continue;
            }
            if (302 === $client->getResponse()->getStatusCode()) {
                $client->followRedirect();
            }
            $this->assertStringNotContainsString('&lt;i data-lucide', (string) $client->getResponse()->getContent(), $route);
        }
    }
}
