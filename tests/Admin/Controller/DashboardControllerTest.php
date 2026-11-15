<?php

namespace App\Tests\Admin\Controller;

use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    public function testAdminDashboardRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin');

        $this->assertResponseRedirects('/login');
    }

    public function testAdminDashboardRendersSuccessfully(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');

        $client->loginUser($testUser);
        $crawler = $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Bienvenue, admin@kiinisawa.com');
        $this->assertSelectorExists('#stat-users');
        $this->assertSelectorExists('#stat-plans');
        $this->assertSelectorExists('#recent-users-table');
    }
}
