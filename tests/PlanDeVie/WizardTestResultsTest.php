<?php

namespace App\Tests\PlanDeVie;

use App\Compte\Entity\User;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\PlanDeVie\Service\TestResultsForPlans;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Cohérence des plans (lot 1) : l'assistant reprend les résultats des tests (profil de personnalité, dons, axes du
 * test Hygiène de Vie) et rappelle les dons dans le Plan de Transmission.
 */
class WizardTestResultsTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->user = $this->createUser(pro: true);
    }

    private function takeTests(): void
    {
        $service = static::getContainer()->get(AssessmentService::class);
        $disc = [];
        foreach (Instruments::items('disc') as $item) {
            $disc[$item['key']] = 'SD';
        }
        $service->record($this->user, 'disc', $disc, 400);
        $gifts = [];
        foreach (Instruments::items('dons-spirituels') as $i => $item) {
            $gifts[$item['key']] = 1 + ($i % 5);
        }
        $service->record($this->user, 'dons-spirituels', $gifts, 400);
        $enn = [];
        foreach (Instruments::items('enneagramme') as $i => $item) {
            $enn[$item['key']] = 1 + ($i * 7 % 5);
        }
        $service->record($this->user, 'enneagramme', $enn, 400);
    }

    private function results(): array
    {
        return static::getContainer()->get(TestResultsForPlans::class)->forUser($this->findUser($this->user->getEmail()));
    }

    public function testPersonalityStepIsPrefilledFromTests(): void
    {
        $this->takeTests();
        $r = $this->results();
        $this->assertNotNull($r['disc']);
        $this->assertNotNull($r['enneagram']);

        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/plan-de-vie/assistant?step=5&single=1');
        $this->assertResponseIsSuccessful();
        $this->assertSame($r['disc']['value'], $crawler->filter('select[name="profile_disc"] option[selected]')->attr('value'));
        $this->assertSame($r['enneagram']['value'], $crawler->filter('select[name="profile_enneagram"] option[selected]')->attr('value'));
        $this->assertSelectorTextContains('main', 'Issu de votre test du');
        $this->assertSelectorTextContains('main', 'Profil DISC');
        // Les 16 profils DISC (et « Je ne sais pas »)
        $this->assertCount(17, $crawler->filter('select[name="profile_disc"] option'));
    }

    public function testSavedValueWinsOverTest(): void
    {
        $this->takeTests();
        $this->client->loginUser($this->user);
        $this->client->request('POST', '/plan-de-vie/assistant?step=5&single=1', ['profile_disc' => 'CS', 'profile_mbti' => '', 'profile_enneagram' => '', 'profile_big5' => '']);
        $crawler = $this->client->request('GET', '/plan-de-vie/assistant?step=5&single=1');
        $this->assertSame('CS', $crawler->filter('select[name="profile_disc"] option[selected]')->attr('value'));
        if ('CS' !== $this->results()['disc']['value']) {
            $this->assertSelectorTextContains('main', 'Votre dernier test');
        }
    }

    public function testWithoutTestsLinksToTests(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/plan-de-vie/assistant?step=5&single=1');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Passer les tests');
        $this->assertSelectorTextContains('main', 'Voir mon profil KIINI SAWA');
        $this->assertSelectorTextNotContains('main', 'Issu de votre test');
        $this->assertCount(0, $crawler->filter('select[name="profile_disc"] option[selected]'));
    }

    public function testGiftsSuggestedInTalentsAndTransmission(): void
    {
        $this->takeTests();
        $gifts = $this->results()['gifts'];
        $this->assertNotEmpty($gifts['names']);

        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/plan-de-vie/assistant?step=4&single=1');
        $chips = $crawler->filter('[data-gift-suggestions] .wiz-chip');
        $this->assertCount(\count($gifts['suggested']), $chips);
        $this->assertSame($gifts['names'][0], $chips->first()->attr('data-value'));

        $this->client->request('GET', '/plan-de-vie/assistant?plan=TRANSMISSION');
        $this->assertSelectorTextContains('[data-gifts-reminder]', $gifts['names'][0]);

        $this->client->request('GET', '/plan-de-transmission');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-gifts-reminder]', $gifts['names'][0]);
    }

    public function testHygienePrioritiesProposed(): void
    {
        $em = $this->em();
        $em->persist((new ProfileTest())->setUser($em->find(User::class, $this->user->getId()))->setType('HYGIENE')->setDate(new \DateTime())->setInsights('')
            ->setResult(json_encode(['axes' => ['physique' => 4.5, 'mental' => 6, 'relationnel' => 8, 'spirituel' => 7.5, 'ressources' => 3, 'environnement' => 6]])));
        $em->flush();

        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/plan-de-vie/assistant?plan=HYGIENE');
        $this->assertResponseIsSuccessful();
        $chips = $crawler->filter('button[onclick^="addSuggestedHygieneDomain"]');
        $this->assertCount(3, $chips);
        $this->assertStringContainsString('3,0/10', $chips->first()->text());
    }

    public function testBalanceExplainedInGrowthPlan(): void
    {
        $this->client->loginUser($this->user);
        $this->client->request('GET', '/plan-de-vie/assistant?plan=CROISSANCE');
        $this->assertSelectorTextContains('[data-croissance-balance]', 'Votre Balance interne vient de votre Plan de Vie');

        $this->client->request('GET', '/plan-de-vie/assistant');
        $this->assertSelectorNotExists('[data-croissance-balance]');
    }

    /** Étape 1 : l'Équation du But du livre (« Je suis créé pour … afin d'apporter … chez … »). */
    public function testPurposeEquationInStepOne(): void
    {
        $this->client->loginUser($this->user);
        $crawler = $this->client->request('GET', '/plan-de-vie/assistant?step=1&single=1');
        $this->assertSelectorTextContains('[data-purpose-equation]', 'Je suis créé pour');
        $this->assertCount(3, $crawler->filter('[data-purpose-equation] input[data-eq]'));
        $this->assertCount(0, $crawler->filter('[data-purpose-equation] input[name]'), 'Les champs de l\'équation ne sont pas enregistrés seuls');
    }
}
