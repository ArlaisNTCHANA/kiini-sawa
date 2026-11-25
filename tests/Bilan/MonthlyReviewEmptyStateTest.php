<?php

namespace App\Tests\Bilan;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Contre-audit visuel du 25/11/2026 : un premier mois sans revue montre un état vide encourageant, pas « 0, —, —, 0/0 ». */
class MonthlyReviewEmptyStateTest extends WebTestCase
{
    use AccountTestTrait;

    public function testFirstMonthShowsAnEncouragingEmptyState(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);

        $client->request('GET', '/bilan/revue-mensuelle');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.ks-empty', 'Votre premier mois commence');
        $this->assertSelectorExists('.ks-empty a[href="/bilan/revue-hebdomadaire"]');
        $this->assertSelectorTextNotContains('body', 'engagement moyen');
    }
}
