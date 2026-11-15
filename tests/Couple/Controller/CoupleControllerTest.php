<?php

namespace App\Tests\Couple\Controller;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CoupleControllerTest extends WebTestCase
{
    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/couple');

        $this->assertResponseRedirects('/login');
    }

    public function testIndexRendersForLoggedUser(): void
    {
        $client = static::createClient();
        $user = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($user);
        $client->request('GET', '/couple');

        $this->assertResponseIsSuccessful();
    }
}
