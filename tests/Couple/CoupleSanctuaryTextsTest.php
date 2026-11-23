<?php

namespace App\Tests\Couple;

use App\Compte\Repository\UserRepository;
use App\Contenu\Entity\SiteText;
use App\Contenu\Entity\SiteTextVersion;
use App\Contenu\Service\SiteTextCatalog;
use App\Couple\Service\CoupleSanctuaryCatalog;
use App\Couple\Service\CoupleService;
use App\Notification\Entity\Notification;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les textes du Sanctuaire du Couple (versets, défis, méditations, signaux) sont modifiables dans
 * Admin › Textes du site : brouillon invisible, publication visible sur le web et dans l'API mobile.
 */
class CoupleSanctuaryTextsTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $em = static::getContainer()->get('doctrine')->getManager();
        $em->createQuery('DELETE '.SiteTextVersion::class.' v')->execute();
        $em->createQuery('DELETE '.SiteText::class.' t')->execute();
        parent::tearDown();
    }

    private function adminEdit(string $code, string $action, string $content): void
    {
        $this->client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $this->client->request('GET', '/admin/textes/modifier?code='.$code);
        $this->assertResponseIsSuccessful();
        $this->client->request('POST', '/admin/textes/modifier?code='.$code, ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'action' => $action, 'content' => $content]);
    }

    private function activeCouple(): array
    {
        $couples = static::getContainer()->get(CoupleService::class);
        $a = $this->createUser(pro: true);
        $b = $this->createUser(pro: true);
        $couple = $couples->create($a, null);
        $couples->join($b, $couple, true);

        return [$couple, $a, $b];
    }

    public function testEveryTextOfTheSanctuaryIsListedInTheAdministration(): void
    {
        $catalog = static::getContainer()->get(SiteTextCatalog::class);
        $defaults = CoupleSanctuaryCatalog::defaults();
        $this->assertGreaterThan(100, \count($defaults));
        foreach (['couple.verset.1.texte', 'couple.defi.ecoute_7j.jour.1', 'couple.meditation.fondations_solides.etape.1.question', 'couple.signal.merci.message'] as $code) {
            $this->assertArrayHasKey($code, $defaults);
            $this->assertSame($defaults[$code], $catalog->find($code)['default'] ?? null, $code);
            $this->assertSame('src/Couple/Service/CoupleSanctuaryCatalog.php', $catalog->find($code)['source']);
        }
        $this->assertStringContainsString('{prenom}', $defaults['couple.signal.merci.message'], 'Le prénom est une variable lisible, pas %s');

        $this->client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $this->client->request('GET', '/admin/textes?groupe=couple');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Sanctuaire du Couple');
        $this->assertSelectorTextContains('main', 'couple.defi.ecoute_7j.jour.1');
    }

    public function testEditedChallengeDayIsShownOnlyOncePublished(): void
    {
        [, $a] = $this->activeCouple();
        $code = 'couple.defi.ecoute_7j.jour.1';
        $this->adminEdit($code, 'draft', 'Brouillon : posez une question ouverte.');
        $this->assertSame('Brouillon : posez une question ouverte.', $this->em()->getRepository(SiteText::class)->findOneBy(['code' => $code])?->getDraft(), 'Le brouillon est bien enregistré');

        $this->client->loginUser($a);
        $this->client->request('POST', '/couple/defis/commencer', ['code' => 'ecoute_7j', '_csrf_token' => FixedCsrfTokenManager::VALUE]);
        $this->client->request('GET', '/couple/defis');
        $this->assertSelectorTextContains('main', CoupleSanctuaryCatalog::CHALLENGES['ecoute_7j']['days'][1]);
        $this->assertSelectorTextNotContains('main', 'Brouillon :');

        $this->adminEdit($code, 'publish', 'Demandez-lui ce qui l\'a rendu heureux aujourd\'hui, et écoutez sans conseiller.');
        $this->client->loginUser($a);
        $this->client->request('GET', '/couple/defis');
        $this->assertSelectorTextContains('main', 'ce qui l\'a rendu heureux aujourd\'hui');
    }

    public function testEditedSignalUsesTheFirstNameVariable(): void
    {
        [, $a, $b] = $this->activeCouple();
        $this->adminEdit('couple.signal.merci.message', 'publish', '{prenom} vous remercie pour votre patience.');

        $this->client->loginUser($a);
        $this->client->request('POST', '/couple/signal', ['preset' => 'merci', '_csrf_token' => FixedCsrfTokenManager::VALUE]);
        $notes = $this->em()->getRepository(Notification::class)->findBy(['user' => $b->getId()]);
        $this->assertSame($a->getDisplayName().' vous remercie pour votre patience.', end($notes)->getMessage());
    }

    public function testMobileApiReturnsTheEditedMeditationTitle(): void
    {
        [, $a] = $this->activeCouple();
        $this->adminEdit('couple.meditation.fondations_solides.titre', 'publish', 'Bâtir notre maison sur le roc');

        $token = static::getContainer()->get(\App\Mobile\Service\MobileTokenService::class)->issue($this->findUser((string) $a->getEmail()), 'Test')['accessToken'];
        $this->client->restart();
        $this->client->request('GET', '/api/mobile/v1/couple/meditations/catalog', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']);
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);
        $titles = array_column($json['catalog'], 'title');
        $this->assertContains('Bâtir notre maison sur le roc', $titles);
    }
}
