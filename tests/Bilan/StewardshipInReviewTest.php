<?php

namespace App\Tests\Bilan;

use App\Bilan\Service\WeeklyReviewService;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Point 7 (26/11/2026) : une priorité d'Intendance passe en revue de la semaine, et soutient l'objectif auquel elle est
 * reliée dans l'analyse de cohérence (plus de piste « objectif non soutenu » à tort).
 */
class StewardshipInReviewTest extends WebTestCase
{
    use AccountTestTrait;

    public function testStewardshipPriorityIsReviewedAndSupportsItsGoal(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $plan = new PlanDeVie();
        $user->setPlanDeVie($plan);
        $goal = (new LifeGoal())->setTitle('Constituer une épargne de sécurité')->setCategory('HAVE')->setPlanDeVie($plan);
        $item = (new StewardshipItem())->setUser($user)->setLabel('Mon épargne')->setPriority(true)->setLifeGoal($goal);
        foreach ([$plan, $goal, $item] as $e) {
            $this->em()->persist($e);
        }
        $this->em()->flush();

        $items = static::getContainer()->get(WeeklyReviewService::class)->items($user);
        $this->assertSame(['Mon épargne'], array_column($items['INTENDANCE'], 'title'));

        $client->loginUser($user);
        $client->request('GET', '/plan-de-vie');
        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString("Constituer une épargne de sécurité' n'est soutenu par aucun", (string) $client->getResponse()->getContent());
    }
}
