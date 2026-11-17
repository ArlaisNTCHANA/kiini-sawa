<?php

namespace App\Tests\Bilan;

use App\Compte\Entity\User;
use App\PlanDeVie\Entity\Season;
use App\PlanDHygiene\Entity\DailyLog;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pratiques du livre : la saison de trois mois (chapitre 11) et le bilan du jour (chapitre 17).
 */
class SeasonAndDailyReviewTest extends WebTestCase
{
    use AccountTestTrait;

    public function testSeasonLifecycle(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/bilan/saison');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('[data-current-season]');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/bilan/saison', ['_csrf_token' => $token, 'action' => 'start', 'theme' => 'Fondations', 'kind' => 'SEMIS']);
        $this->assertResponseRedirects('/bilan/saison');
        $client->request("GET", "/bilan/saison");
        $this->assertSelectorTextContains('[data-current-season]', 'Fondations');
        $this->assertSelectorTextContains('[data-current-season]', 'Semer avec fidélité, se former, bâtir les fondations');

        $this->em()->clear();
        $season = $this->em()->getRepository(Season::class)->findOneBy(['user' => $user->getId()]);
        $this->assertSame('SEMIS', $season->getKind());
        $this->assertSame((new \DateTime('today +3 months'))->format('Y-m-d'), $season->getEndDate()->format('Y-m-d'));

        // Fin de saison : la revue est proposée
        $season->setEndDate(new \DateTime('today +5 days'));
        $this->em()->flush();
        $crawler = $client->request('GET', '/bilan/saison');
        $this->assertSelectorExists('[data-season-review]');
        $client->request('POST', '/bilan/saison', ['_csrf_token' => $token, 'action' => 'review', 'review_note' => 'Des racines solides']);
        $this->em()->clear();
        $this->assertNotNull($this->em()->getRepository(Season::class)->findOneBy(['user' => $user->getId()])->getReviewedAt());
    }

    public function testDailyReviewIsSavedWithTheDayIntention(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/bilan/du-jour');
        $this->assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/bilan/du-jour', [
            '_csrf_token' => $token, 'intention' => 'Écouter avant de répondre', 'intention_kept' => '1',
            'energy' => '4', 'mood' => '5', 'stress' => '2', 'gratitude' => 'Un repas en famille',
        ]);
        $this->assertResponseRedirects('/bilan/du-jour');

        $this->em()->clear();
        $plan = $this->em()->find(User::class, $user->getId())->getPlanDHygiene();
        $log = $this->em()->getRepository(DailyLog::class)->findOneBy(['planDHygiene' => $plan, 'date' => new \DateTime('today')]);
        $this->assertTrue($log->isIntentionKept());
        $this->assertSame(5, $log->getMoodLevel());
        $this->assertSame('Un repas en famille', $log->getGratitude());

        $client->request('GET', '/bilan/du-jour');
        $this->assertSelectorTextContains('table', 'Un repas en famille');
    }
}
