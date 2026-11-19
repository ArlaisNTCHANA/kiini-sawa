<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\Instruments;
use App\Personnalite\Psychometrie\IpipItemBank;
use App\Personnalite\Psychometrie\Scorer;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Moteur psychométrique : banques d'items, calcul des scores, type, style, qualité, rapports.
 */
class PsychometricsTest extends WebTestCase
{
    use AccountTestTrait;

    /** Réponses « profil » : chaque facette listée est très présente (5 sur items +, 1 sur items −), les autres absentes. */
    private function answersFor(string $slug, array $highFacets): array
    {
        $answers = [];
        foreach (Instruments::items($slug) as $item) {
            [$bank, $id] = explode(':', $item['key']);
            if ('ipip' !== $bank) {
                $answers[$item['key']] = 3;
                continue;
            }
            [$facet, $keyed] = IpipItemBank::ITEMS[(int) $id];
            $high = \in_array($facet, $highFacets, true);
            $answers[$item['key']] = ($high xor -1 === $keyed) ? 5 : 1;
        }

        return $answers;
    }

    public function testItemBanksAndInstruments(): void
    {
        $this->assertCount(116, IpipItemBank::ITEMS);
        $this->assertCount(29, IpipItemBank::FACETS);
        foreach (IpipItemBank::FACETS as $facet => $label) {
            $this->assertCount(4, IpipItemBank::itemsOfFacet($facet), "4 items pour $facet");
        }
        $expected = ['profil-complet' => 116, 'big-five' => 58, 'type' => 48, 'style' => 52, 'disc' => 24, 'enneagramme' => 72, 'dons-spirituels' => 60];
        foreach ($expected as $slug => $count) {
            $items = Instruments::items($slug);
            $this->assertCount($count, $items, $slug);
            $this->assertCount($count, array_unique(array_column($items, 'key')), "$slug sans doublon");
        }
    }

    public function testScoringReverseKeysTypeAndStyle(): void
    {
        $scorer = new Scorer();
        // Extraverti, intuitif, sentiment, jugement ; meneur
        $scores = $scorer->ipip($this->answersFor('profil-complet', ['E1', 'E2', 'E6', 'O1', 'O4', 'O5', 'A3', 'A6', 'O3', 'C2', 'C5', 'C6', 'E3', 'E4']));

        $this->assertSame(100.0, $scores['facets']['E1']['score']);
        $this->assertSame(0.0, $scores['facets']['N1']['score']);
        $this->assertSame(100.0, $scores['facets']['C6']['score'], 'Items inversés de Prudence correctement recodés');
        $this->assertSame('ENFJ', $scores['type']['code']);
        $this->assertSame(100, $scores['type']['axes']['EI']['clarity']);
        $this->assertContains($scores['style']['primary'], ['D', 'I']);

        $introvert = $scorer->ipip($this->answersFor('type', ['C2', 'C5', 'C6']));
        $this->assertSame('ISTJ', $introvert['type']['code']);
    }

    public function testQualityChecks(): void
    {
        $scorer = new Scorer();
        $same = array_fill_keys(array_column(Instruments::items('profil-complet'), 'key'), 4);
        $q = $scorer->quality($same, 60);
        $this->assertFalse($q['reliable']);
        $this->assertGreaterThanOrEqual(2, \count($q['warnings']), 'Réponses identiques et trop rapides');

        $good = $this->answersFor('profil-complet', ['E1', 'C2']);
        $this->assertTrue($scorer->quality($good, 900)['reliable']);
    }

    public function testTakeTestGetReportAndPdf(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/mes-tests/questionnaire/type');
        $this->assertResponseIsSuccessful();
        $this->assertCount(48, $crawler->filter('fieldset.as-q'));
        $token = $crawler->filter('input[name=_csrf_token]')->attr('value');

        // Réponses incomplètes refusées
        $client->request('POST', '/mes-tests/questionnaire/type', ['answers' => ['ipip:25' => 5], '_csrf_token' => $token, 'startedAt' => time() - 600]);
        $this->assertResponseRedirects('/mes-tests/questionnaire/type');

        $client->request('POST', '/mes-tests/questionnaire/type', ['answers' => $this->answersFor('type', ['E1', 'E2', 'E6']), '_csrf_token' => $token, 'startedAt' => time() - 600]);
        $assessment = $this->em()->getRepository(Assessment::class)->findOneBy(['user' => $user->getId()]);
        $this->assertResponseRedirects('/mes-tests/rapport/'.$assessment->getId());
        $client->followRedirect();
        $this->assertAnySelectorTextContains('h2', 'ESTP · Le Fonceur');
        $this->assertAnySelectorTextContains('h2', 'Bon usage de ce rapport');
        $this->assertSelectorTextNotContains('body', 'Architecte');

        // Résultat repris par les plans (type stocké pour le Plan de Vie)
        $pt = $this->em()->getRepository(ProfileTest::class)->findOneBy(['user' => $user->getId(), 'type' => 'MBTI']);
        $this->assertSame('ESTP', json_decode($pt->getResult(), true)['type']);

        $client->request('GET', '/mes-tests/rapport/'.$assessment->getId().'/pdf');
        $this->assertResponseHeaderSame('content-type', 'application/pdf');

        $client->loginUser($this->createUser(pro: true));
        $client->request('GET', '/mes-tests/rapport/'.$assessment->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnswersAccumulateAcrossTests(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);
        foreach (['type' => ['E1', 'E2', 'E6'], 'style' => ['E3', 'E4']] as $slug => $high) {
            $crawler = $client->request('GET', '/mes-tests/questionnaire/'.$slug);
            $client->request('POST', '/mes-tests/questionnaire/'.$slug, ['answers' => $this->answersFor($slug, $high), '_csrf_token' => $crawler->filter('input[name=_csrf_token]')->attr('value'), 'startedAt' => time() - 600]);
        }
        $last = $this->em()->getRepository(Assessment::class)->findOneBy(['user' => $user->getId(), 'instrument' => 'style']);
        $scores = $last->getScores();
        $this->assertArrayHasKey('O1', $scores['facets'], 'Les réponses du test « type » complètent le profil');
        $this->assertNotNull($scores['type']);
        $this->assertNotNull($scores['style']);
    }

    public function testEnneagramAndGifts(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $crawler = $client->request('GET', '/mes-tests/questionnaire/enneagramme');
        $answers = [];
        foreach (Instruments::items('enneagramme') as $item) {
            $id = (int) explode(':', $item['key'])[1];
            $answers[$item['key']] = \App\Personnalite\Psychometrie\EnneagramBank::ITEMS[$id][0] === 9 ? 5 : 2;
        }
        $client->request('POST', '/mes-tests/questionnaire/enneagramme', ['answers' => $answers, '_csrf_token' => $crawler->filter('input[name=_csrf_token]')->attr('value'), 'startedAt' => time() - 600]);
        $client->followRedirect();
        $this->assertAnySelectorTextContains('h2', 'Type 9 · L\'Artisan de paix');
        $this->assertAnySelectorTextContains('h2', 'Bon usage de ce rapport');

        $crawler = $client->request('GET', '/mes-tests/questionnaire/dons-spirituels');
        $answers = [];
        foreach (Instruments::items('dons-spirituels') as $item) {
            $id = (int) explode(':', $item['key'])[1];
            $answers[$item['key']] = \App\Personnalite\Psychometrie\SpiritualGiftsBank::ITEMS[$id][0] === 'HOSPITALITE' ? 5 : 2;
        }
        $client->request('POST', '/mes-tests/questionnaire/dons-spirituels', ['answers' => $answers, '_csrf_token' => $crawler->filter('input[name=_csrf_token]')->attr('value'), 'startedAt' => time() - 600]);
        $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Hospitalité');
        $this->assertAnySelectorTextContains('h2', 'Mettre vos dons au service');
    }

    public function testLegacyLinksRedirectToNewQuestionnaires(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $client->request('GET', '/mes-tests/mbti/passer');
        $this->assertResponseRedirects('/mes-tests/questionnaire/type', 301);
        $client->request('GET', '/mes-tests/');
        $this->assertSelectorTextContains('main', 'Profil complet');
        $this->assertSelectorTextNotContains('main', '16 Personnalités');
    }
}
