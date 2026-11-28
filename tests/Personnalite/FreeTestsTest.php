<?php

namespace App\Tests\Personnalite;

use App\Abonnement\Service\Entitlements;
use App\Admin\Entity\AppConfig;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Offre gratuite : deux tests complets (DISC, Dons spirituels par défaut, réglable dans Admin › Réglages › Réglages de l'application) ; les autres, les PDF des
 * rapports et les lectures croisées sont réservés à PRO. Les résultats déjà obtenus restent toujours consultables.
 */
class FreeTestsTest extends WebTestCase
{
    use AccountTestTrait;

    protected function tearDown(): void
    {
        $config = $this->em()->getRepository(AppConfig::class)->find('default');
        if ($config) {
            $config->setFreeTests(AppConfig::DEFAULT_FREE_TESTS);
            $this->em()->flush();
        }
        parent::tearDown();
    }

    private function answers(string $slug): array
    {
        $a = [];
        foreach (Instruments::items($slug) as $item) {
            $a[$item['key']] = 'enneagramme' === $slug || 'dons-spirituels' === $slug ? 3 : 'IS';
        }

        return $a;
    }

    public function testDefaultsAreDiscAndSpiritualGifts(): void
    {
        static::createClient();
        $this->assertSame(['disc', 'dons-spirituels'], static::getContainer()->get(Entitlements::class)->freeTests());
        $this->assertSame('disc', static::getContainer()->get(Entitlements::class)->firstFreeTest());
    }

    public function testAFreeUserCanTakeAFreeTestButNotAProTest(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());

        $client->request('GET', '/mes-tests/questionnaire/disc');
        $this->assertResponseIsSuccessful();
        $client->request('GET', '/mes-tests/questionnaire/dons-spirituels');
        $this->assertResponseIsSuccessful();

        foreach (['enneagramme', 'profil-complet', 'big-five', 'type'] as $slug) {
            $client->request('GET', '/mes-tests/questionnaire/'.$slug);
            $this->assertResponseRedirects('/abonnement?fonction=tests', null, $slug.' est réservé à PRO');
            $client->request('POST', '/mes-tests/questionnaire/'.$slug, ['answers' => ['x' => 1]]);
            $this->assertResponseRedirects('/abonnement?fonction=tests', null, $slug.' : envoi refusé aussi');
        }
    }

    public function testProCanTakeEveryTestAndTrialCountsAsPro(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $client->request('GET', '/mes-tests/questionnaire/enneagramme');
        $this->assertResponseIsSuccessful();
    }

    public function testTheTestsPageShowsTheLocksAndKeepsPastResults(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        // Un résultat obtenu avant la limite (ou pendant l'essai PRO) reste consultable
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'enneagramme', $this->answers('enneagramme'), 600);
        $client->loginUser($user);

        $crawler = $client->request('GET', '/mes-tests/');
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThanOrEqual(3, $crawler->filter('a[href*="fonction=tests"]')->count(), 'les tests PRO affichent un verrou');
        $this->assertStringContainsString('/mes-tests/questionnaire/disc', $crawler->html());

        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertResponseIsSuccessful('le rapport déjà obtenu reste lisible');
        $this->assertSelectorNotExists('a[href$="/pdf"]');
        $this->assertSelectorExists('a[href*="fonction=test_pdf"]');
        $this->assertSelectorNotExists('a[href$="/mes-tests/questionnaire/enneagramme"]', 'pas de « Repasser le test » pour un test PRO');
    }

    public function testReportPdfIsProOnly(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $this->answers('disc'), 600);
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/rapport/'.$a->getId().'/pdf');
        $this->assertResponseRedirects('/abonnement?fonction=test_pdf');

        $client->restart();
        $proUser = $this->createUser(pro: true);
        $b = static::getContainer()->get(AssessmentService::class)->record($proUser, 'disc', $this->answers('disc'), 600);
        $client->loginUser($proUser);
        $client->request('GET', '/mes-tests/rapport/'.$b->getId().'/pdf');
        $this->assertResponseIsSuccessful();
        $this->assertSame('application/pdf', $client->getResponse()->headers->get('Content-Type'));
    }

    public function testTheListIsAnAdminSetting(): void
    {
        static::createClient();
        $config = $this->em()->getRepository(AppConfig::class)->find('default') ?? new AppConfig();
        $config->setFreeTests(['enneagramme']);
        $this->em()->persist($config);
        $this->em()->flush();
        $ent = static::getContainer()->get(Entitlements::class);
        $this->assertSame(['enneagramme'], $ent->freeTests());
        $this->assertSame('enneagramme', $ent->firstFreeTest());
        $this->assertFalse($ent->canTakeTest(null, 'disc'));
        $this->assertTrue($ent->canTakeTest(null, 'enneagramme'));
    }

    public function testThePricingPageListsTheFreeTests(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/abonnement');
        $this->assertResponseIsSuccessful();
        $text = $client->getCrawler()->filter('table.ab-table')->text();
        $this->assertStringContainsString('DISC, Dons spirituels', $text);
        $this->assertStringContainsString('Les autres tests de personnalité', $text);
    }

    public function testDiscReportInvitesToProInsteadOfALockedTest(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $this->answers('disc'), 600);
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Regard croisé');
        $this->assertSelectorTextContains('body', 'Avec KIINI SAWA PRO');
        $this->assertStringNotContainsString('/mes-tests/questionnaire/big-five', $client->getResponse()->getContent());
    }

    public function testTheAdminCanChooseTheFreeTests(): void
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(\App\Compte\Repository\UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $crawler = $client->request('GET', '/admin/config');
        $this->assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer les modifications')->form();
        foreach ($form['app_config[freeTests]'] as $box) {
            $box->untick();
        }
        foreach ($form['app_config[freeTests]'] as $box) {
            if ('enneagramme' === $box->availableOptionValues()[0]) {
                $box->tick();
            }
        }
        $client->submit($form);
        $this->assertResponseRedirects('/admin/config');
        $this->em()->clear();
        $this->assertSame(['enneagramme'], static::getContainer()->get(Entitlements::class)->freeTests());
    }
}
