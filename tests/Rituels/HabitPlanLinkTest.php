<?php

namespace App\Tests\Rituels;

use App\PlanDHygiene\Entity\HygieneDomain;
use App\PlanDHygiene\Entity\PlanDHygiene;
use App\Rituels\Entity\Habit;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Point 7 (26/11/2026) : un rituel se relie à un objectif de n'importe lequel des plans, Hygiène comprise, et à un seul.
 */
class HabitPlanLinkTest extends WebTestCase
{
    use AccountTestTrait;

    public function testHabitCanBeLinkedToAnHygieneDomainAndUnlinked(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $plan = (new PlanDHygiene())->setUser($user);
        $user->setPlanDHygiene($plan);
        $domain = (new HygieneDomain())->setName('Sommeil');
        $plan->addHygieneDomain($domain);
        $habit = (new Habit())->setTitle('Coucher à 22 h 30')->setUser($user);
        foreach ([$plan, $domain, $habit] as $e) {
            $this->em()->persist($e);
        }
        $this->em()->flush();

        $client->loginUser($user);
        $client->request('GET', '/mes-plans/rituels');
        // Le panneau est dans un <template> Alpine : on vérifie le code de la page
        $this->assertStringContainsString('<option value="HYGIENE:'.$domain->getId().'">Sommeil</option>', (string) $client->getResponse()->getContent());

        $post = fn (string $link) => $client->request('POST', '/api/habits/'.$habit->getId().'/rooting', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['link' => $link]));
        $post('HYGIENE:'.$domain->getId());
        $this->assertResponseIsSuccessful();
        $this->em()->clear();
        $this->assertSame($domain->getId(), $this->em()->find(Habit::class, $habit->getId())->getHygieneDomain()?->getId());

        // Un objectif d'un autre compte est refusé (le rituel redevient libre), et « aucun » délie
        $post('HYGIENE:999999');
        $this->em()->clear();
        $this->assertNull($this->em()->find(Habit::class, $habit->getId())->getHygieneDomain());
    }
}
