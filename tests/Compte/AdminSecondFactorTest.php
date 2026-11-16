<?php

namespace App\Tests\Compte;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Connexion en deux étapes des administrateurs : sans le code reçu par email, aucune page n'est accessible.
 */
class AdminSecondFactorTest extends WebTestCase
{
    use AccountTestTrait;

    protected function setUp(): void
    {
        $_SERVER['ADMIN_SECOND_FACTOR'] = $_ENV['ADMIN_SECOND_FACTOR'] = '1';
    }

    protected function tearDown(): void
    {
        $_SERVER['ADMIN_SECOND_FACTOR'] = $_ENV['ADMIN_SECOND_FACTOR'] = '0';
        $this->cleanUpUsers();
        parent::tearDown();
    }

    public function testAdminMustEnterTheEmailedCode(): void
    {
        $client = static::createClient();
        $admin = $this->createUser();
        $admin->setRoles(['ROLE_ADMIN']);
        $this->em()->flush();
        $client->loginUser($admin);

        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/connexion/code');
        $client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/connexion/code');

        $client->request('GET', '/connexion/code');
        $this->assertResponseIsSuccessful();
        $code = $this->codeFrom($this->lastEmail());

        $client->submitForm('Valider', ['code' => '000000' === $code ? '111111' : '000000']);
        $client->followRedirect();
        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/connexion/code', null, 'Un mauvais code ne suffit pas');

        $client->request('GET', '/connexion/code');
        $client->submitForm('Valider', ['code' => $code]);
        $this->assertResponseRedirects('/dashboard');
        $client->request('GET', '/admin');
        $this->assertResponseIsSuccessful();
    }

    public function testRegularUsersAreNotAffected(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
    }
}
