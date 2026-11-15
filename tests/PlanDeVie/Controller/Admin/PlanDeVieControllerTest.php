<?php

namespace App\Tests\PlanDeVie\Controller\Admin;

use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\LifeMilestone;
use App\PlanDeVie\Entity\Value;
use App\Compte\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PlanDeVieControllerTest extends WebTestCase
{
    public function testPlanDeVieRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/plan-de-vie');

        $this->assertResponseRedirects('/login');
    }

    public function testPlanDeVieRendersSuccessfully(): void
    {
        $client = static::createClient();
        
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');

        $client->loginUser($testUser);
        $client->request('GET', '/admin/plan-de-vie');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('nav.ks-tabs');
        
        // Assert seeded values are displayed
        $this->assertSelectorTextContains('body', 'Excellence');
        $this->assertSelectorTextContains('body', 'Intégrité');
        
        // Assert seeded life goals are displayed
        $this->assertSelectorTextContains('body', 'Devenir un coach certifié ICF');
    }

    public function testCrudOperations(): void
    {
        $client = static::createClient();
        
        $userRepository = static::getContainer()->get(UserRepository::class);
        $testUser = $userRepository->findOneByEmail('admin@kiinisawa.com');
        $client->loginUser($testUser);

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        
        // Defensive cleanup of leftovers
        $leftoverMs = $em->getRepository(LifeMilestone::class)->findOneBy(['title' => 'Jalon Test']);
        if ($leftoverMs) {
            $em->remove($leftoverMs);
        }
        $leftoverVal1 = $em->getRepository(Value::class)->findOneBy(['name' => 'Nouvelle Valeur Test']);
        if ($leftoverVal1) {
            $em->remove($leftoverVal1);
        }
        $leftoverVal2 = $em->getRepository(Value::class)->findOneBy(['name' => 'Valeur Modifiée']);
        if ($leftoverVal2) {
            $em->remove($leftoverVal2);
        }
        $em->flush();

        // 1. Test adding a value
        $crawler = $client->request('GET', '/admin/plan-de-vie');
        // Get CSRF token for adding value from the form input
        $csrfToken = $crawler->filter('form[action="/admin/plan-de-vie/value/add"] input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/admin/plan-de-vie/value/add', [
            'name' => 'Nouvelle Valeur Test',
            'behavior' => 'Comportement test pour la valeur.',
            '_csrf_token' => $csrfToken,
        ]);

        $this->assertResponseRedirects('/admin/plan-de-vie');
        $crawler = $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Nouvelle Valeur Test');

        // 2. Test editing the value
        /** @var Value $value */
        $value = $em->getRepository(Value::class)->findOneBy(['name' => 'Nouvelle Valeur Test']);
        $this->assertNotNull($value);

        $editCsrfToken = $crawler->filter('form[action="/admin/plan-de-vie/value/' . $value->getId() . '/edit"] input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/admin/plan-de-vie/value/' . $value->getId() . '/edit', [
            'name' => 'Valeur Modifiée',
            'behavior' => 'Comportement modifié.',
            '_csrf_token' => $editCsrfToken,
        ]);

        $this->assertResponseRedirects('/admin/plan-de-vie');
        $crawler = $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Valeur Modifiée');

        // 3. Test adding a milestone to a goal
        /** @var LifeGoal $goal */
        $goal = $em->getRepository(LifeGoal::class)->findOneBy(['title' => 'Devenir un coach certifié ICF']);
        $this->assertNotNull($goal);

        $addMsCsrfToken = $crawler->filter('form[action="/admin/plan-de-vie/milestone/' . $goal->getId() . '/add"] input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/admin/plan-de-vie/milestone/' . $goal->getId() . '/add', [
            'title' => 'Jalon Test',
            'dueDate' => '2026-12-31',
            '_csrf_token' => $addMsCsrfToken,
        ]);

        $this->assertResponseRedirects('/admin/plan-de-vie');
        $crawler = $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Jalon Test');

        // 4. Test toggling the milestone (AJAX)
        /** @var LifeMilestone $milestone */
        $milestone = $em->getRepository(LifeMilestone::class)->findOneBy(['title' => 'Jalon Test']);
        $this->assertNotNull($milestone);
        $this->assertFalse($milestone->isCompleted());

        $client->request('POST', '/admin/plan-de-vie/milestone/toggle/' . $milestone->getId(), [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();
        $responseData = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($responseData['success']);
        $this->assertTrue($responseData['completed']);
        $this->assertEquals(100, $responseData['goalProgress']); // Since it's the only milestone, goal progress should become 100%

        // Check DB state
        $em->clear();
        $updatedMilestone = $em->getRepository(LifeMilestone::class)->find($milestone->getId());
        $this->assertTrue($updatedMilestone->isCompleted());

        // Final cleanup
        $em->remove($updatedMilestone);
        $createdValue = $em->getRepository(Value::class)->findOneBy(['name' => 'Valeur Modifiée']);
        if ($createdValue) {
            $em->remove($createdValue);
        }
        $em->flush();
    }
}
