<?php

namespace App\Tests\Organisation;

use App\Abonnement\Service\Entitlements;
use App\Admin\Entity\AppConfig;
use App\Compte\Repository\UserRepository;
use App\Organisation\Entity\Membership;
use App\Organisation\Entity\Organisation;
use App\Organisation\Entity\SeatOrder;
use App\Organisation\Service\OrganisationService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Offre Organisation : création, invitation, consentement, rôles, confidentialité, places.
 */
class OrganisationTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $config = $this->em()->getRepository(AppConfig::class)->find('default');
        $config->setBankTransferInstructions('IBAN FR76 0000')->setOrgSeatYearlyPriceEur(30)->setOrgSeatYearlyPriceXaf(10000);
        $this->em()->flush();
    }

    private function orgs(): OrganisationService
    {
        return static::getContainer()->get(OrganisationService::class);
    }

    private function licensed(int $seats = 10): array
    {
        $owner = $this->createUser();
        $org = $this->orgs()->create($owner, 'Église Test '.uniqid(), 'EGLISE');
        $org->setSeats($seats)->setSeatsValidUntil(new \DateTimeImmutable('+6 months'));
        $this->em()->flush();

        return [$org, $owner];
    }

    public function testPackPricesWithVolumeDiscount(): void
    {
        $this->assertSame(300.0, $this->orgs()->packPrice(10, 'EUR'));
        $this->assertSame(2250.0, $this->orgs()->packPrice(100, 'EUR'), '−25 % à partir de 100 places');
        $this->assertSame(750000.0, $this->orgs()->packPrice(100, 'XAF'));
    }

    public function testCreateOrganisationFromTheApp(): void
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/organisation');
        $this->assertSelectorTextContains('main', 'Packs de places');
        $this->client->submit($crawler->selectButton('Créer l\'organisation')->form(), ['name' => 'Église de Bonapriso', 'type' => 'EGLISE']);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Église de Bonapriso');
        $this->assertSelectorTextContains('main', 'Inviter des membres');
    }

    public function testInvitationLinkJoinWithConsentAndPro(): void
    {
        [$org] = $this->licensed();
        $member = $this->createUser();

        // Sans compte : page d'accueil de l'invitation, puis retour après connexion
        $this->client->request('GET', '/rejoindre/'.$org->getInviteCode());
        $this->assertSelectorTextContains('h1', 'vous invite');
        $this->client->loginUser($member);
        $crawler = $this->client->request('GET', '/rejoindre/'.$org->getInviteCode());
        $this->client->submit($crawler->selectButton('Rejoindre '.$org->getName())->form(), ['sharePlans' => '1']);
        $this->assertResponseRedirects('/organisation?org='.$org->getId());

        $m = $this->orgs()->membershipOf($member, $org);
        $this->assertTrue($m->isActive());
        $this->assertTrue($m->isSharePlans());
        $this->assertTrue(static::getContainer()->get(Entitlements::class)->isPro($this->findUser($member->getEmail())), 'Une place rend PRO');

        // Licence expirée : retour en gratuit
        $org = $this->em()->find(Organisation::class, $org->getId());
        $org->setSeatsValidUntil(new \DateTimeImmutable('-1 day'));
        $this->em()->flush();
        $this->assertFalse(static::getContainer()->get(Entitlements::class)->isPro($this->findUser($member->getEmail())));
    }

    public function testFullOrganisationRefusesNewMembers(): void
    {
        [$org] = $this->licensed(1); // la seule place est prise par le responsable
        $this->expectException(\DomainException::class);
        $this->orgs()->join($this->createUser(), $org, false);
    }

    public function testPrivacyRulesForLeadersAndMentors(): void
    {
        [$org, $owner] = $this->licensed();
        $mentorUser = $this->createUser();
        $sharing = $this->createUser();
        $private = $this->createUser();
        $other = $this->createUser();
        $mentor = $this->orgs()->join($mentorUser, $org, false)->setRole('MENTOR');
        $mSharing = $this->orgs()->join($sharing, $org, true)->setMentor($mentor);
        $mPrivate = $this->orgs()->join($private, $org, false);
        $mOther = $this->orgs()->join($other, $org, true);
        $this->em()->flush();
        $ownerM = $this->orgs()->membershipOf($owner, $org);

        // Le responsable voit tout le monde, mais ne lit que les plans partagés
        $this->assertCount(5, $this->orgs()->visibleMembers($ownerM));
        $this->assertTrue($this->orgs()->canReadPlans($ownerM, $mSharing));
        $this->assertFalse($this->orgs()->canReadPlans($ownerM, $mPrivate));

        // L'accompagnateur ne voit que les membres qui lui sont confiés
        $visible = array_map(fn (Membership $m) => $m->getId(), $this->orgs()->visibleMembers($mentor));
        $this->assertContains($mSharing->getId(), $visible);
        $this->assertNotContains($mOther->getId(), $visible);
        $this->assertFalse($this->orgs()->canReadPlans($mentor, $mOther), 'Partage accepté, mais membre non confié');

        // En HTTP : plan partagé lisible, plan privé refusé, journal jamais exposé
        $this->client->loginUser($owner);
        $this->client->request('POST', '/plan-de-vie/assistant?step=1&single=1'); // assure un Plan de Vie au responsable
        $this->client->loginUser($sharing);
        $this->client->request('POST', '/plan-de-vie/assistant?step=1&single=1', ['purpose_statement' => 'Servir les jeunes de mon quartier.']);
        $this->client->jsonRequest('POST', '/api/journal/entries/create', ['content' => 'Pensée très intime']);

        $this->client->loginUser($owner);
        $this->client->request('GET', "/organisation/{$org->getId()}/membre/{$mSharing->getId()}/plan");
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Servir les jeunes de mon quartier.', $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('Pensée très intime', $this->client->getResponse()->getContent());

        $this->client->request('GET', "/organisation/{$org->getId()}/membre/{$mPrivate->getId()}/plan");
        $this->assertResponseStatusCodeSame(403);

        // Un simple membre ne voit pas le tableau des membres
        $this->client->loginUser($other);
        $this->client->request('GET', '/organisation');
        $this->assertSelectorTextNotContains('main', 'Inviter des membres');
        $this->assertSelectorTextContains('main', 'Ma place dans');
    }

    public function testSeatOrderValidatedByAdmin(): void
    {
        $owner = $this->createUser();
        $org = $this->orgs()->create($owner, 'Association Test '.uniqid(), 'ASSOCIATION');
        $this->client->loginUser($owner);
        $crawler = $this->client->request('GET', '/organisation');
        $token = $crawler->filter('form[action$="/places"] input[name=_csrf_token]')->attr('value');
        $reference = 'VIR-ORG-'.uniqid();
        $this->client->request('POST', "/organisation/{$org->getId()}/places", ['seats' => 25, 'currency' => 'XAF', 'method' => 'BANK_TRANSFER', 'reference' => $reference, '_csrf_token' => $token]);
        $order = $this->em()->getRepository(SeatOrder::class)->findOneBy(['reference' => $reference]);
        $this->assertSame(225000.0, $order->getAmount(), '25 places × 10 000 FCFA − 10 %');

        $admin = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/paiements');
        $this->assertSelectorTextContains('body', $reference);
        $this->client->submit($crawler->filter('form[action="/admin/commandes-places/'.$order->getId().'/valider"]')->form());

        $this->em()->clear();
        $org = $this->em()->find(Organisation::class, $org->getId());
        $this->assertTrue($org->isLicenseValid());
        $this->assertSame(25, $org->getSeats());
        $this->assertSame((new \DateTimeImmutable('today +1 year'))->format('Y-m-d'), $org->getSeatsValidUntil()->format('Y-m-d'));
        $this->assertMatchesRegularExpression('/^KS-\d{4}-\d{5}$/', $this->em()->find(SeatOrder::class, $order->getId())->getReceiptNumber());

        $this->client->loginUser($this->findUser($owner->getEmail()));
        $this->client->request('GET', "/organisation/{$org->getId()}/recu/{$order->getId()}");
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }

    public function testOnlyManagersCanManage(): void
    {
        [$org] = $this->licensed();
        $member = $this->createUser();
        $m = $this->orgs()->join($member, $org, false);
        $this->client->loginUser($member);
        $crawler = $this->client->request('GET', '/organisation');
        $token = $crawler->filter('input[name=_csrf_token]')->first()->attr('value');
        $this->client->request('POST', "/organisation/{$org->getId()}/membre/{$m->getId()}", ['action' => 'role', 'role' => 'OWNER', '_csrf_token' => $token]);
        $this->assertResponseStatusCodeSame(403);
    }

    public function testExportOfMentorDoesNotLeakOtherMembers(): void
    {
        [$org, $owner] = $this->licensed();
        $member = $this->createUser();
        $m = $this->orgs()->join($member, $org, true)->setMentor($this->orgs()->membershipOf($owner, $org));
        $this->em()->flush();

        $this->client->loginUser($owner);
        $this->client->request('GET', '/profil/export');
        $data = json_decode($this->client->getResponse()->getContent(), true)['donnees'];
        $memberships = $data['Organisation/Membership'] ?? [];
        $this->assertCount(1, $memberships, 'Seule l\'appartenance du responsable est exportée');
        $this->assertSame($owner->getId(), $memberships[0]['userId']);
    }
}
