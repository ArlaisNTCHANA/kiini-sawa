<?php

namespace App\Tests\Rituels\Controller;

use App\Rituels\Entity\Habit;
use App\Rituels\Entity\HabitLog;
use App\Compte\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HabitControllerTest extends WebTestCase
{
    public function testRituelsRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mes-plans/rituels');

        $this->assertResponseRedirects('/login');
    }

    public function testRituelsPageRendersSuccessfully(): void
    {
        $client = static::createClient();
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        $client->request('GET', '/mes-plans/rituels');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="habit"]');
    }

    public function testHabitLifecycleAndLogRooting(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $userRepository = $container->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        // 1. Create a habit via form submission
        $crawler = $client->request('GET', '/mes-plans/rituels');
        $form = $crawler->selectButton('Créer le rituel')->form([
            'habit[title]' => 'Habitude Test de Méditation',
            'habit[frequency]' => 'DAILY',
        ]);
        $client->submit($form);

        // Find the created habit
        $habit = $em->getRepository(Habit::class)->findOneBy([
            'title' => 'Habitude Test de Méditation',
            'user' => $testUser
        ], ['id' => 'DESC']);
        $this->assertNotNull($habit);
        // Point 7 : l'enracinement du nouveau rituel s'ouvre pour le relier à un plan
        $this->assertResponseRedirects('/mes-plans/rituels?relier='.$habit->getId());
        $client->followRedirect();
        $this->assertStringContainsString('id="rooting-link"', (string) $client->getResponse()->getContent());

        // 2. Log completion for today
        $client->request('POST', '/api/habits/' . $habit->getId() . '/log', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'status' => true
        ]));

        $this->assertResponseIsSuccessful();
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertTrue($response['isCompleted']);

        // Check in database
        $em->clear();
        $habit = $em->getRepository(Habit::class)->find($habit->getId());
        $this->assertCount(1, $habit->getLogs());
        $this->assertTrue($habit->getLogs()[0]->isStatus());

        // 3. Update habit rooting details
        $client->request('POST', '/api/habits/' . $habit->getId() . '/rooting', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'implementationWhen' => 'Le matin à 7h',
            'implementationThen' => 'Je médite pendant 10 minutes',
            'supportStrategy' => 'Mettre une alarme',
            'rootingIntention' => 'Être calme',
            'rootingAction' => 'Méditer',
            'rootingMoment' => 'Réveil',
            'rootingAnchor' => 'Mon lit',
            'rootingBenefit' => 'Moins de stress',
            'rootingFacilitator' => 'Application KIINI',
            'frequency' => 'DAILY',
            'recurrenceDay' => null,
            'recurrenceWeek' => null,
            'endDate' => null
        ]));

        $this->assertResponseIsSuccessful();
        $responseRooting = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseRooting['success']);

        // Verify updated values in db
        $em->clear();
        $updatedHabit = $em->getRepository(Habit::class)->find($habit->getId());
        $this->assertEquals('Le matin à 7h', $updatedHabit->getImplementationWhen());
        $this->assertEquals('Je médite pendant 10 minutes', $updatedHabit->getImplementationThen());

        // 4. Delete the habit
        $client->request('DELETE', '/api/habits/' . $habit->getId());
        $this->assertResponseIsSuccessful();
        $responseDelete = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseDelete['success']);

        // Verify deleted
        $em->clear();
        $deletedHabit = $em->getRepository(Habit::class)->find($habit->getId());
        $this->assertNull($deletedHabit);
    }
}
