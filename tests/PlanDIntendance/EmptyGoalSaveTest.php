<?php

namespace App\Tests\PlanDIntendance;

use App\PlanDIntendance\Entity\StewardshipItem;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Enregistrer un élément avec « Aucun objectif précis » (valeur vide) ne doit plus renvoyer une erreur 400. */
class EmptyGoalSaveTest extends WebTestCase
{
    use AccountTestTrait;

    public function testSavingWithoutGoalWorks(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $item = (new StewardshipItem())->setUser($user)->setLabel('Mon vélo');
        $this->em()->persist($item);
        $this->em()->flush();

        $client->loginUser($user);
        $client->request('POST', '/plan-d-intendance/element/'.$item->getId(), [
            '_csrf_token' => FixedCsrfTokenManager::VALUE, 'status' => 'DORT', 'action' => 'Vérifier les freins', 'life_goal' => '', 'priority' => '0',
        ]);
        $this->assertResponseRedirects();
        $this->em()->clear();
        $this->assertSame('Vérifier les freins', $this->em()->find(StewardshipItem::class, $item->getId())->getActionText());
    }
}
