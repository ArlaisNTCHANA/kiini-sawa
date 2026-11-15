<?php

namespace App\Tests\Admin\Controller;

use App\Admin\Entity\AppConfig;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ConfigurationControllerTest extends WebTestCase
{
    public function testAdminConfigRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/config');

        $this->assertResponseRedirects('/login');
    }

    public function testAdminConfigRendersSuccessfully(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');

        $client->loginUser($testUser);
        $crawler = $client->request('GET', '/admin/config');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Paramètres de la Plateforme');
        $this->assertSelectorExists('textarea[name="app_config[defaultPurposeStatement]"]');
    }

    public function testAdminConfigFormSubmission(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');

        $client->loginUser($testUser);
        $crawler = $client->request('GET', '/admin/config');

        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        $form['app_config[defaultPurposeStatement]'] = 'Nouveau But de Dieu par défaut';
        $form['app_config[proMonthlyPrice]'] = 19.99;
        $form['app_config[proYearlyPrice]'] = 179.99;
        $form['app_config[trialDurationDays]'] = 30;
        $form['app_config[demoPassword]'] = 'secret123';

        $client->submit($form);
        $this->assertResponseRedirects('/admin/config');

        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'La configuration de la plateforme a été mise à jour avec succès.');

        // Verify database entry
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $config = $em->getRepository(AppConfig::class)->find('default');
        $this->assertEquals('Nouveau But de Dieu par défaut', $config->getDefaultPurposeStatement());
        $this->assertEquals(19.99, $config->getProMonthlyPrice());
        $this->assertEquals(179.99, $config->getProYearlyPrice());
        $this->assertEquals(30, $config->getTrialDurationDays());
        $this->assertEquals('secret123', $config->getDemoPassword());
    }
}
