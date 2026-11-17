<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\DiscBank;
use App\Personnalite\Psychometrie\DiscCombos;
use App\Personnalite\Psychometrie\DiscProfiles;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test DISC à choix forcé : profil naturel (« le moins » inversé), profil adapté (« le plus »), écart, rapport complet.
 */
class DiscTest extends WebTestCase
{
    use AccountTestTrait;

    public function testBankIsBalanced(): void
    {
        $this->assertCount(24, DiscBank::BLOCKS);
        $words = [];
        foreach (DiscBank::BLOCKS as $block => $list) {
            $this->assertCount(4, $list);
            $shuffled = DiscBank::shuffled($block);
            $this->assertEqualsCanonicalizing(DiscBank::LETTERS, array_column($shuffled, 'letter'), "Bloc $block : une lettre de chaque");
            $words = array_merge($words, $list);
        }
        $this->assertCount(96, array_unique($words), 'Aucun mot répété');
        // L'ordre d'affichage varie : la première position n'est pas toujours la même lettre
        $firsts = array_map(fn ($b) => DiscBank::shuffled($b)[0]['letter'], array_keys(DiscBank::BLOCKS));
        $this->assertCount(4, array_unique($firsts));
        // 16 profils : 4 marqués + 12 combinaisons, tous complets
        $this->assertCount(16, DiscCombos::PROFILES);
        $fields = array_keys(DiscCombos::PROFILES['D']);
        foreach (DiscCombos::PROFILES as $code => $p) {
            $this->assertSame($fields, array_keys($p), "Profil $code complet");
            $this->assertCount(2, $p['figures'], "Deux figures bibliques pour $code");
            $this->assertCount(3, $p['actions']);
            if (2 === \strlen($code)) {
                $this->assertNotSame($code[0], $code[1]);
            }
        }
    }

    public function testNaturalAndAdaptedProfiles(): void
    {
        // Le plus : toujours D (adapté D) ; le moins : toujours C (naturel sans C) ; le S n'est jamais choisi
        $answers = array_fill_keys(array_map(fn ($b) => 'disc:'.$b, array_keys(DiscBank::BLOCKS)), 'DC');
        $s = DiscBank::score($answers);
        $this->assertSame(100, $s['adapted']['D']);
        $this->assertSame(0, $s['natural']['C']);
        $this->assertSame(100, array_sum($s['natural']));
        $this->assertSame(100, array_sum($s['adapted']));
        $this->assertSame('D', $s['adaptedPrimary']);
        $this->assertGreaterThan(25, $s['gap']);

        // Mêmes choix pour le plus et pour le moins inversé : écart faible
        $mixed = [];
        foreach (array_keys(DiscBank::BLOCKS) as $b) {
            $mixed['disc:'.$b] = 0 === $b % 2 ? 'IC' : 'SC';
        }
        $m = DiscBank::score($mixed);
        $this->assertContains($m['primary'], ['I', 'S']);
        $this->assertNotNull($m['secondary']);
    }

    public function testTakeTestAndReadFullReport(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $crawler = $client->request('GET', '/mes-tests/questionnaire/disc');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Le plus');
        $this->assertSelectorTextContains('body', 'Le moins');

        $answers = [];
        foreach (Instruments::items('disc') as $i => $item) {
            $answers[$item['key']] = $i < 20 ? 'IC' : 'DC';
        }
        $token = $crawler->filter('input[name=_csrf_token]')->attr('value');

        // Réponse invalide (même mot pour le plus et le moins) : refusée
        $bad = $answers;
        $bad['disc:3'] = 'II';
        $client->request('POST', '/mes-tests/questionnaire/disc', ['answers' => $bad, '_csrf_token' => $token, 'startedAt' => time() - 600]);
        $this->assertResponseRedirects('/mes-tests/questionnaire/disc');
        $this->assertSame(0, $this->em()->getRepository(Assessment::class)->count(['user' => $user->getId()]));

        // Sans l'accord sur les conditions d'utilisation : refusé
        $client->request('POST', '/mes-tests/questionnaire/disc', ['answers' => $answers, '_csrf_token' => $token, 'startedAt' => time() - 600]);
        $this->assertResponseRedirects('/mes-tests/questionnaire/disc');

        $client->request('POST', '/mes-tests/questionnaire/disc', ['answers' => $answers, 'consent' => '1', '_csrf_token' => $token, 'startedAt' => time() - 600]);
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        foreach (['Profil naturel et profil adapté', 'Sous pression', 'Comment vous percevez les autres', 'Des figures bibliques', 'Pour grandir', 'En résumé', 'Bon usage de ce rapport', 'Pas de décision importante', 'Influent'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringNotContainsString('DiSC', $html, 'Pas de marque déposée');

        $pt = $this->em()->getRepository(ProfileTest::class)->findOneBy(['user' => $user->getId(), 'type' => 'DISC']);
        $this->assertNotNull($pt);
        $this->assertStringStartsWith('Influent', $pt->getInsights());

        // L'ancienne adresse du test et le rapport « DISC » mènent au nouveau test
        $client->request('GET', '/mes-tests/DISC/passer');
        $this->assertResponseRedirects('/mes-tests/questionnaire/disc', 301);

        $a = $this->em()->getRepository(Assessment::class)->findOneBy(['user' => $user->getId(), 'instrument' => 'disc']);
        $client->request('GET', '/mes-tests/rapport/'.$a->getId().'/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testEveryProfileRendersAFullReport(): void
    {
        static::bootKernel();
        $builder = static::getContainer()->get(\App\Personnalite\Psychometrie\ReportBuilder::class);
        $twig = static::getContainer()->get('twig');
        foreach (array_keys(DiscCombos::PROFILES) as $code) {
            $natural = ['D' => 15, 'I' => 15, 'S' => 15, 'C' => 15];
            $natural[$code[0]] = isset($code[1]) ? 35 : 55;
            if (isset($code[1])) {
                $natural[$code[1]] = 20;
            }
            $natural[array_key_first(array_diff_key($natural, array_flip(str_split($code))))] += 100 - array_sum($natural);
            $scores = ['natural' => $natural, 'adapted' => $natural, 'most' => [], 'least' => [], 'primary' => $code[0], 'secondary' => $code[1] ?? null,
                'adaptedPrimary' => $code[0], 'gap' => 0, 'shifts' => array_fill_keys(DiscBank::LETTERS, 0)];
            $html = $twig->render('personnalite/assessment/disc/report_pdf.html.twig', ['instrument' => Instruments::CATALOG['disc'], 'slug' => 'disc',
                'assessment' => ['takenAt' => new \DateTime()], 'quality' => [], 'name' => 'Test'] + $builder->disc($scores));
            $this->assertStringContainsString(htmlspecialchars(DiscCombos::PROFILES[$code]['name'], \ENT_QUOTES), $html, $code);
            $this->assertStringContainsString('Dans la même famille', $html, "Figures de la même famille pour $code");
            $this->assertStringContainsString('Bon usage de ce rapport', $html);
        }
    }

    public function testCatalogShowsDiscInsteadOfOldStyle(): void
    {
        $this->assertContains('disc', Instruments::GROUPS['Personnalité']);
        $this->assertNotContains('style', array_merge(...array_values(Instruments::GROUPS)));
        $this->assertTrue(Instruments::exists('style'), 'Les anciens rapports restent lisibles');
    }
}
