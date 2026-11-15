<?php

namespace App\Tests\Shared;

use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Corrections fonctionnelles de la phase 1 (voir docs/audit-2026-11.md, §2.3).
 */
class FunctionalFixesTest extends WebTestCase
{
    private function loggedClient(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    /** F1 : un utilisateur connecté qui revient sur /login va au dashboard. */
    public function testLoggedUserVisitingLoginGoesToDashboard(): void
    {
        $client = $this->loggedClient();
        $client->request('GET', '/login');

        $this->assertResponseRedirects('/dashboard');
    }

    /** Aucun lien interne de la navigation ne doit mener à une page inexistante. */
    public function testNavigationHasNoDeadLinks(): void
    {
        $client = $this->loggedClient();
        $crawler = $client->request('GET', '/mes-plans/rituels');
        $this->assertResponseIsSuccessful();

        $links = array_unique(array_filter(
            $crawler->filter('nav a[href^="/"], header a[href^="/"], .dd-item[href^="/"]')->each(fn ($a) => $a->attr('href')),
            fn ($href) => '/logout' !== $href
        ));
        $this->assertNotEmpty($links);

        foreach ($links as $href) {
            $client->request('GET', $href);
            $this->assertNotSame(404, $client->getResponse()->getStatusCode(), "Lien mort dans le menu : $href");
        }
    }

    /** Une date illisible renvoie 400 et non 500. */
    public function testInvalidDateReturns400(): void
    {
        $client = $this->loggedClient();
        $client->request('POST', '/api/planner/toggle/TASK/1', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['date' => 'pas-une-date']));

        $this->assertResponseStatusCodeSame(400);
        $this->assertStringContainsString('Date invalide', $client->getResponse()->getContent());
    }

    /** F7 : l'invitation ne révèle pas si un compte existe. */
    public function testInviteUnknownEmailDoesNotRevealAccountExistence(): void
    {
        $client = $this->loggedClient();
        $client->request('POST', '/api/social/connection/add', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['email' => 'inconnu-'.uniqid().'@exemple.org']));

        $this->assertResponseIsSuccessful();
        $this->assertTrue(json_decode($client->getResponse()->getContent(), true)['success']);
    }

    /** F5 : app:create-user crée un utilisateur simple, administrateur seulement avec --admin. */
    public function testCreateUserCommandRoles(): void
    {
        $kernel = static::bootKernel();
        $tester = new CommandTester((new Application($kernel))->find('app:create-user'));
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $simple = 'Simple-'.uniqid().'@Exemple.org';
        $tester->execute(['email' => $simple, 'password' => 'mot-de-passe-test']);
        $admin = 'admin-'.uniqid().'@exemple.org';
        $tester->execute(['email' => $admin, 'password' => 'mot-de-passe-test', '--admin' => true]);

        $simpleUser = $em->getRepository(User::class)->findOneBy(['email' => strtolower($simple)]);
        $adminUser = $em->getRepository(User::class)->findOneBy(['email' => $admin]);

        $this->assertNotNull($simpleUser, 'Email stocké en minuscules');
        $this->assertNotContains('ROLE_ADMIN', $simpleUser->getRoles());
        $this->assertContains('ROLE_ADMIN', $adminUser->getRoles());

        $em->remove($simpleUser);
        $em->remove($adminUser);
        $em->flush();
    }
}
