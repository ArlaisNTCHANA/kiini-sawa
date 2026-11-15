<?php

namespace App\Tests\Organisation\Controller;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class OrganisationControllerTest extends WebTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/organisation');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexRendersForLoggedUser(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($user);
        $client->request('GET', '/organisation');

        $this->assertResponseIsSuccessful();
    }
}
