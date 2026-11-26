<?php

namespace App\Tests\Social;

use App\Social\Entity\AccountabilityCommitment;
use App\Social\Entity\Connection;
use App\Social\Service\PartnerDirectory;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Point 6 (26/11/2026) : « Partenaires » et « Ma redevabilité » ne font plus qu'une rubrique, « Mes Partenaires de
 * Redevabilité », avec une fiche par personne et une seule invitation.
 */
class PartnersPageTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    public function testOldAccountabilityAddressLeadsToTheSinglePage(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/social/redevabilite');
        $this->assertResponseRedirects('/social#partenaires');

        $crawler = $this->client->request('GET', '/social');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mes Partenaires de Redevabilité');
        $this->assertStringNotContainsString('Ma redevabilité', $crawler->filter('body')->text());
        $this->assertSelectorExists('a[href="/social/inviter"]');
    }

    public function testAMemberWhoSharesAndFollowsACommitmentHasOneCard(): void
    {
        $owner = $this->createUser();
        $friend = $this->createUser();
        $connection = (new Connection())->setRequester($owner)->setReceiver($friend)->setStatus('ACCEPTED');
        $this->em()->persist($connection);
        $this->em()->flush();

        $this->client->loginUser($owner);
        $this->client->request('POST', '/social/redevabilite/creer', ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'target' => '', 'freeLabel' => 'Épargner 10 %', 'connectionId' => $connection->getId(), 'consent' => '1']);
        $this->client->request('POST', '/social/redevabilite/creer', ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'target' => '', 'freeLabel' => 'Courir', 'connectionId' => 0, 'partnerName' => 'Paul', 'partnerEmail' => 'paul-'.uniqid().'@exemple.org', 'consent' => '1']);

        $crawler = $this->client->request('GET', '/social');
        $card = $crawler->filter('#partenaire-u'.$friend->getId());
        $this->assertCount(1, $card);
        $this->assertStringContainsString('Membre de KIINI SAWA', $card->text());
        $this->assertStringContainsString('Ce que je lui montre', $card->text());
        $this->assertStringContainsString('Épargner 10 %', $card->text());
        // L'offre gratuite ne permet qu'un engagement ouvert : « Courir » n'a pas été créé, Paul n'a pas de fiche
        $this->assertCount(1, $crawler->filter('.pr-person'));
    }

    public function testAPersonWithoutAccountAppearsByEmail(): void
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);
        $this->client->request('POST', '/social/redevabilite/creer', ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'target' => '', 'freeLabel' => 'Courir', 'connectionId' => 0, 'partnerName' => 'Paul', 'partnerEmail' => 'paul-'.uniqid().'@exemple.org', 'consent' => '1']);
        $c = $this->em()->getRepository(AccountabilityCommitment::class)->findOneBy(['owner' => $owner->getId()]);

        $crawler = $this->client->request('GET', '/social');
        $card = $crawler->filter('.pr-person');
        $this->assertCount(1, $card);
        $this->assertStringContainsString('Paul', $card->text());
        $this->assertStringContainsString('Par e-mail', $card->text());
        $this->assertCount(1, $card->filter('#engagement-'.$c->getId()));
        $this->assertTrue(static::getContainer()->get(PartnerDirectory::class)->hasAny($this->findUser((string) $owner->getEmail())));
    }

    public function testSingleInvitationSendsTheShareRequestAndPrefillsTheCommitment(): void
    {
        $owner = $this->createUser();
        $friend = $this->createUser();
        $this->client->loginUser($owner);

        $this->client->request('GET', '/social/inviter');
        $this->assertResponseIsSuccessful();
        $this->client->request('POST', '/social/inviter', ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'name' => 'Marie', 'email' => strtoupper((string) $friend->getEmail()), 'engagement' => '1', 'share' => '1']);
        $this->assertResponseRedirects('/social/redevabilite/nouveau');
        $this->assertNotNull($this->em()->getRepository(Connection::class)->findOneBy(['requester' => $owner->getId(), 'receiver' => $friend->getId(), 'status' => 'PENDING']));

        $crawler = $this->client->request('GET', '/social/redevabilite/nouveau');
        $this->assertSame('Marie', $crawler->filter('input[name="partnerName"]')->attr('value'));
        $this->assertSame($friend->getEmail(), $crawler->filter('input[name="partnerEmail"]')->attr('value'));
    }

    public function testShareOnlyInvitationNeverRevealsWhetherAnAccountExists(): void
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);
        $this->client->request('POST', '/social/inviter', ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'name' => 'Inconnu', 'email' => 'personne-'.uniqid().'@exemple.org', 'share' => '1']);
        $this->assertResponseRedirects('/social');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('[role="status"]', 'Si ');
        $this->assertSame(0, $this->em()->getRepository(Connection::class)->count(['requester' => $owner->getId()]));

        // Aucune des deux façons cochée : rien ne part
        $this->client->request('POST', '/social/inviter', ['_csrf_token' => FixedCsrfTokenManager::VALUE, 'name' => 'Paul', 'email' => 'paul@exemple.org']);
        $this->assertResponseStatusCodeSame(422);
    }

    public function testMenuHasASingleEntry(): void
    {
        $this->client->loginUser($this->createUser());
        $crawler = $this->client->request('GET', '/social');
        $this->assertCount(0, $crawler->filter('a[href="/social/redevabilite"]'));
        $this->assertGreaterThan(0, $crawler->filter('a[href="/social"]')->count());
    }

    /** L'aperçu répond par une page (pas une redirection) : Turbo doit laisser le navigateur l'afficher. */
    public function testCommitmentFormIsNotSubmittedThroughTurbo(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/social/redevabilite/nouveau');
        $this->assertSelectorExists('form[action="/social/redevabilite/apercu"][data-turbo="false"]');
    }
}
