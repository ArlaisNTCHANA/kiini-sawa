<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Service\InsightEngine;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Moteur de lectures croisées : chaque règle ne se déclenche que si ses données la justifient et cite ses preuves. */
class InsightEngineTest extends WebTestCase
{
    use AccountTestTrait;

    private function ids(array $found): array
    {
        return array_column($found, 'id');
    }

    private function bf(array $levels): array
    {
        $domains = [];
        foreach ($levels as $d => $level) {
            $domains[$d] = ['level' => $level, 'value' => 'high' === $level ? 80 : ('low' === $level ? 20 : 50)];
        }

        return ['bf' => ['domains' => $domains]];
    }

    private function gifts(array $codes): array
    {
        return ['gifts' => ['top' => array_map(fn ($c) => ['code' => $c, 'name' => ucfirst(strtolower($c))], $codes)]];
    }

    public function testNothingWithoutTests(): void
    {
        $this->assertSame([], (new InsightEngine())->analyze(['disc' => null, 'bigfive' => null, 'type' => null, 'enneagramme' => null, 'dons' => null]));
    }

    public function testRevealedGiftAndReservedTemperament(): void
    {
        $r = ['disc' => null, 'type' => null, 'enneagramme' => null, 'bigfive' => $this->bf(['E' => 'low']), 'dons' => $this->gifts(['ENSEIGNEMENT'])];
        $found = (new InsightEngine())->analyze($r);
        $this->assertSame(['public-gift-quiet'], $this->ids($found));
        $this->assertSame(['dons', 'bigfive'], $found[0]['sources']);
        $this->assertSame('Recoupé par 2 tests', $found[0]['confidence']);
        $this->assertNotEmpty($found[0]['question']);
        $this->assertSame('CROISSANCE', $found[0]['step']['plan']);
        // Extraversion élevée : la règle ne s'applique plus
        $r['bigfive'] = $this->bf(['E' => 'high']);
        $this->assertNotContains('public-gift-quiet', $this->ids((new InsightEngine())->analyze($r)));
    }

    public function testSensitivityWithCareGiftIsAVigilance(): void
    {
        $r = ['disc' => null, 'type' => null, 'enneagramme' => null, 'bigfive' => $this->bf(['N' => 'high']), 'dons' => $this->gifts(['MISERICORDE'])];
        $found = (new InsightEngine())->analyze($r);
        $this->assertSame('risk', $found[0]['kind']);
        $this->assertSame('HYGIENE', $found[0]['step']['plan']);
        // Sans le don de soin, aucune lecture
        $r['dons'] = $this->gifts(['ENSEIGNEMENT']);
        $this->assertSame([], (new InsightEngine())->analyze($r));
    }

    public function testEnneagramExpectationsCoverAllTypes(): void
    {
        $this->assertSame(range(1, 9), array_keys(InsightEngine::ENNEAGRAM_EXPECTS));
        foreach (InsightEngine::ENNEAGRAM_EXPECTS as $letters) {
            $this->assertNotEmpty(array_intersect($letters, ['D', 'I', 'S', 'C']));
        }
    }

    public function testRankedAndLimited(): void
    {
        $r = ['disc' => null, 'type' => null, 'enneagramme' => null,
            'bigfive' => $this->bf(['E' => 'low', 'N' => 'high', 'A' => 'low', 'C' => 'high', 'O' => 'high']),
            'dons' => $this->gifts(['ENSEIGNEMENT', 'MISERICORDE', 'SERVICE', 'SAGESSE'])];
        $found = (new InsightEngine())->analyze($r);
        $this->assertLessThanOrEqual(InsightEngine::MAX, \count($found));
        $weights = array_column($found, 'weight');
        $sorted = $weights;
        rsort($sorted);
        $this->assertSame($sorted, $weights, 'Classées de la plus importante à la moindre');
    }

    public function testSynthesisAndReportsShowInsightsForPro(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $svc = static::getContainer()->get(AssessmentService::class);
        $ids = [];
        foreach (['disc' => 'SD', 'dons-spirituels' => 1, 'enneagramme' => 7, 'profil-complet' => 7] as $slug => $m) {
            $a = [];
            foreach (Instruments::items($slug) as $i => $it) {
                $a[$it['key']] = 'disc' === $slug ? 'SD' : 1 + ($i * $m % 5);
            }
            $ids[$slug] = $svc->record($user, $slug, $a, 600)->getId();
        }
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/synthese');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Lectures croisées');
        $this->assertSelectorTextContains('main', 'Une question à vous poser');
        $client->request('GET', '/mes-tests/rapport/'.$ids['enneagramme']);
        $this->assertSelectorTextContains('body', 'Ce que vos autres tests ajoutent');
        $client->request('GET', '/mes-tests/rapport/'.$ids['disc'].'/pdf');
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }

    public function testFreeUserSeesOnlyATeaser(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $svc = static::getContainer()->get(AssessmentService::class);
        $ids = [];
        foreach (['disc' => 'SD', 'enneagramme' => 7] as $slug => $m) {
            $a = [];
            foreach (Instruments::items($slug) as $i => $it) {
                $a[$it['key']] = 'disc' === $slug ? 'SD' : 1 + ($i * $m % 5);
            }
            $ids[$slug] = $svc->record($user, $slug, $a, 600)->getId();
        }
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/rapport/'.$ids['enneagramme']);
        $this->assertSelectorTextContains('body', 'avec l\'offre PRO');
        $this->assertSelectorTextNotContains('body', 'Une question à vous poser');
    }

    public function testInternalBigFivePairs(): void
    {
        $facets = fn (array $f) => array_map(fn ($level) => ['label' => 'Facette', 'level' => $level], $f);
        $r = ['disc' => null, 'type' => null, 'enneagramme' => null, 'dons' => null,
            'bigfive' => ['bf' => ['domains' => ['E' => ['level' => 'mid', 'value' => 50, 'facets' => $facets(['E3' => 'high', 'E2' => 'low'])]]]]];
        $found = (new InsightEngine())->analyze($r);
        $this->assertSame(['authority-no-crowd'], $this->ids($found));
        $this->assertTrue($found[0]['internal']);
        $this->assertSame(['bigfive'], $found[0]['sources']);
        $this->assertSame('Lu dans ce seul test : à recouper', $found[0]['confidence']);
        // Facette au mauvais niveau : rien
        $r['bf'] = null;
        $r['bigfive']['bf']['domains']['E']['facets'] = $facets(['E3' => 'high', 'E2' => 'mid']);
        $this->assertSame([], (new InsightEngine())->analyze($r));
    }

    public function testInternalGiftPairsNeedBothGifts(): void
    {
        $base = ['disc' => null, 'type' => null, 'enneagramme' => null, 'bigfive' => null];
        $found = (new InsightEngine())->analyze($base + ['dons' => $this->gifts(['PROPHETIE', 'MISERICORDE', 'SERVICE'])]);
        $this->assertContains('truth-and-mercy', $this->ids($found));
        $this->assertSame([], (new InsightEngine())->analyze($base + ['dons' => $this->gifts(['PROPHETIE', 'SERVICE'])]));
    }

    public function testVocationNuancesDependOnTemperament(): void
    {
        self::bootKernel();
        $method = new \ReflectionMethod(\App\Personnalite\Service\ProfileSynthesis::class, 'vocationNuances');
        $synthesis = static::getContainer()->get(\App\Personnalite\Service\ProfileSynthesis::class);
        $out = $method->invoke($synthesis, ['bigfive' => $this->bf(['E' => 'low', 'O' => 'high', 'C' => 'high', 'N' => 'high'])]);
        $this->assertCount(4, $out);
        $this->assertSame([], $method->invoke($synthesis, ['bigfive' => null]));
        $this->assertCount(1, $method->invoke($synthesis, ['bigfive' => $this->bf(['E' => 'mid', 'N' => 'high'])]));
    }
}
