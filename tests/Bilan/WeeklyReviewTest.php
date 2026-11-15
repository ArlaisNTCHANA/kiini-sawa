<?php

namespace App\Tests\Bilan;

use App\Bilan\Entity\WeeklyReview;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\GrowthGoalScore;
use App\PlanDeTransmission\Entity\LegacyGoal;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Revue hebdomadaire sur les quatre plans, une par semaine, reportée dans les plans et le journal.
 */
class WeeklyReviewTest extends WebTestCase
{
    use AccountTestTrait;

    public function testReviewCoversAllPlansAndUpdatesThem(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        // Un élément dans chacun des quatre plans
        $client->request('POST', '/plan-de-vie/assistant?plan=CROISSANCE&step=9&single=1', ['growth_character_title' => ['Patience']]);
        $client->request('POST', '/plan-de-vie/assistant?plan=DEVELOPPEMENT&step=8&single=1', ['growth_competence_title' => ['Prise de parole']]);
        $client->request('POST', '/plan-de-vie/assistant?plan=TRANSMISSION&step=11&single=1', ['legacy_title' => ['Bourse d\'études']]);
        $client->request('POST', '/plan-de-vie/assistant?plan=HYGIENE&step=12&single=1', ['hygiene_name' => ['Sommeil']]);

        $crawler = $client->request('GET', '/bilan/revue-hebdomadaire');
        $this->assertResponseIsSuccessful();
        foreach (['Plan de Croissance', 'Plan de Développement', 'Plan de Transmission', 'Plan d\'Hygiène de Vie', 'Patience', 'Prise de parole', 'Bourse d\'études', 'Sommeil'] as $text) {
            $this->assertSelectorTextContains('main', $text);
        }

        $u = $this->findUser($user->getEmail());
        $patience = $u->getPlanDeCroissance()->getGrowthGoals()->filter(fn (GrowthGoal $g) => 'Patience' === $g->getTitle())->first();
        $legacy = $u->getPlanDeTransmission()->getLegacyGoals()->first();

        $form = $crawler->selectButton('Enregistrer ma revue')->form();
        $client->submit($form, [
            'wins' => 'J\'ai tenu mes rituels du matin',
            'lesson' => 'Préparer la veille',
            "score[GROWTH_{$patience->getId()}]" => '8',
            "progress[GROWTH_{$patience->getId()}]" => '30',
            "score[LEGACY_{$legacy->getId()}]" => '6',
            "progress[LEGACY_{$legacy->getId()}]" => '10',
            'priorities[0]' => 'Finir le dossier de bourse',
        ]);
        $this->assertResponseRedirects('/bilan/revue-hebdomadaire');
        $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Revue de la semaine enregistrée (moyenne 7/10)');

        $em = $this->em();
        $em->clear();
        $review = $em->getRepository(WeeklyReview::class)->findOneBy(['user' => $user->getId()]);
        $this->assertSame(['Finir le dossier de bourse'], $review->getPriorities());
        $this->assertCount(2, $review->getItems());
        $this->assertSame(30, $em->find(GrowthGoal::class, $patience->getId())->getProgress());
        $this->assertSame(10, $em->find(LegacyGoal::class, $legacy->getId())->getProgress());
        $this->assertSame(8, $em->getRepository(GrowthGoalScore::class)->findOneBy(['growthGoal' => $patience->getId()])->getScore(), 'Historique des évaluations du Bilan alimenté');
        $this->assertCount(1, $this->findUser($user->getEmail())->getReflectionNotes(), 'La revue est aussi notée dans le journal');

        // Une seule revue par semaine : un second envoi la complète
        $crawler = $client->request('GET', '/bilan/revue-hebdomadaire');
        $client->submit($crawler->selectButton('Enregistrer ma revue')->form(), ['lesson' => 'Préparer la veille, toujours']);
        $this->assertSame(1, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM weekly_review WHERE user_id = ?', [$user->getId()]));
        $this->assertCount(1, $this->findUser($user->getEmail())->getReflectionNotes());
    }
}
