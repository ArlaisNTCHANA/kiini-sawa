<?php

namespace App\Tests\Admin;

use App\Admin\Twig\AdminNavigationExtension;
use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Routing\RouterInterface;

/**
 * Gabarit commun de l'administration (refonte du 23/11/2026) : chaque page du menu s'ouvre avec le même cadre
 * (menu, fil d'Ariane, titre), aucune page d'administration n'est oubliée, et les gabarits n'ont plus de couleur en dur.
 */
class AdminNavigationTest extends WebTestCase
{
    private function admin(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    public function testEveryMenuPageOpensWithTheCommonLayout(): void
    {
        $client = $this->admin();
        $router = static::getContainer()->get(RouterInterface::class);
        foreach (AdminNavigationExtension::GROUPS as $group) {
            foreach ($group['items'] as [$label, $route]) {
                $crawler = $client->request('GET', $router->generate($route));
                $this->assertResponseIsSuccessful($label);
                $this->assertCount(1, $crawler->filter('aside.ks-admin-side'), $label.' : menu de l\'administration');
                $this->assertCount(1, $crawler->filter('nav.ks-admin-crumbs'), $label.' : fil d\'Ariane');
                $this->assertCount(1, $crawler->filter('h1.ks-header__title'), $label.' : un seul titre');
                $active = $crawler->filter('.ks-admin-nav__link.is-active');
                $this->assertCount(1, $active, $label.' : une seule entrée active dans le menu');
                $this->assertStringContainsString($label, $active->text());
                $this->assertLessThanOrEqual(1, $crawler->filter('.ks-admin-head .ks-btn--primary')->count(), $label.' : une seule action principale en tête de page');
            }
        }
    }

    public function testEveryAdminPageIsReachableFromTheMenu(): void
    {
        $inMenu = [];
        foreach (AdminNavigationExtension::GROUPS as $group) {
            foreach ($group['items'] as $it) {
                array_push($inMenu, ...$it[3]);
            }
        }
        $inMenu[] = 'admin_plan_de_vie'; // ancienne page, gardée hors menu (décision du fondateur, 23/11/2026)
        $missing = [];
        foreach (static::getContainer()->get(RouterInterface::class)->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/admin') || !\in_array('GET', $route->getMethods() ?: ['GET'], true)) {
                continue;
            }
            $covered = array_filter($inMenu, fn ($p) => str_starts_with($p, '=') ? $name === substr($p, 1) : ($name === $p || str_starts_with($name, $p.'_')));
            if (!$covered) {
                $missing[] = $name;
            }
        }
        $this->assertSame([], $missing, 'Page d\'administration absente du menu (AdminNavigationExtension::GROUPS).');
    }

    public function testDashboardListsWhatIsWaiting(): void
    {
        $client = $this->admin();
        $crawler = $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
        $todo = static::getContainer()->get(AdminNavigationExtension::class)->counts();
        if (0 === $todo['transactions'] + $todo['orders'] + $todo['feedback'] + $todo['drafts']) {
            $this->assertSelectorTextContains('.ks-todo', 'Tout est à jour');
        } else {
            $this->assertGreaterThan(0, $crawler->filter('a.ks-todo__item')->count());
        }
    }

    public function testDocumentationUsesTheAdminLayout(): void
    {
        $crawler = $this->admin()->request('GET', '/admin/doc');
        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('aside.ks-admin-side'));
        $this->assertGreaterThan(3, $crawler->filter('.doc-nav a')->count());
    }

    public function testAdminTemplatesHaveNoHardCodedColorsNorEnglishLabels(): void
    {
        $root = \dirname(__DIR__, 2).'/templates';
        $found = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = $file->getPathname();
            $src = (string) file_get_contents($path);
            if (!str_ends_with($path, '.twig') || !str_contains($src, "extends 'admin/layout.html.twig'")) {
                continue;
            }
            $rel = str_replace($root.'/', '', $path);
            if (preg_match('/#[0-9a-fA-F]{6}\b|rgba\((?!27, 27, 27)/', $src, $m)) {
                $found[] = $rel.' : couleur en dur '.$m[0];
            }
            if (preg_match('/>\s*(User|Admin|Logs?|Dashboard|Settings)\s*</', $src, $m)) {
                $found[] = $rel.' : libellé anglais '.trim($m[1]);
            }
        }
        $this->assertSame([], $found, "Utiliser les variables de templates/shared/_design_tokens.html.twig et des libellés en français :\n".implode("\n", $found));
    }

    /** Ancienne page « Plan de Vie » de l'administration : elle plantait à la première visite d'un administrateur sans plan. */
    public function testOldPlanDeVieAdminPageOpensOnFirstVisit(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $admin = (new User())->setEmail('admin-neuf-'.uniqid().'@exemple.org')->setPassword('non-utilise')->setRoles(['ROLE_ADMIN'])->setIsVerified(true);
        $em->persist($admin);
        $em->flush();
        $client->loginUser($admin);
        $client->request('GET', '/admin/plan-de-vie');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.ks-admin-crumbs', 'ancienne page');
    }
}
