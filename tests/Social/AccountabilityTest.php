<?php

namespace App\Tests\Social;

use App\Notification\Entity\Notification;
use App\Rituels\Entity\Habit;
use App\Social\Entity\AccountabilityCommitment;
use App\Social\Entity\AccountabilityNudge;
use App\Social\Entity\Connection;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Engagements de redevabilité (lot 1) : consentement, accord du partenaire, interpellation avec copie, réponse en un clic.
 */
class AccountabilityTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** En test, les jetons CSRF ont une valeur fixe (tests/Support/FixedCsrfTokenManager). */
    private function token(string $page = ''): string
    {
        return \App\Tests\Support\FixedCsrfTokenManager::VALUE;
    }

    private function createCommitment(array $fields): void
    {
        $this->client->request('POST', '/social/redevabilite/creer', $fields + ['_csrf_token' => $this->token()]);
    }

    /** @return list<\Symfony\Component\Mime\Email> */
    private function emailsTo(string $address): array
    {
        return array_values(array_filter($this->getMailerMessages(), fn ($m) => $m instanceof \Symfony\Component\Mime\Email && $m->getTo()[0]->getAddress() === $address));
    }

    public function testPreviewShowsWhatWillBeSentAndConsentIsRequired(): void
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);
        $this->client->request('POST', '/social/redevabilite/apercu', [
            '_csrf_token' => $this->token(), 'target' => '', 'freeLabel' => 'Courir trois fois par semaine',
            'why' => 'Avoir de l\'énergie pour ma famille', 'connectionId' => 0, 'partnerName' => 'Paul', 'partnerEmail' => 'paul-'.uniqid().'@exemple.org',
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Rien d\'autre ne lui sera envoyé tant qu\'il ou elle n\'aura pas accepté');
        $this->assertSelectorTextContains('main', 'Vous recevrez une copie');
        $this->assertSelectorTextContains('main', 'Courir trois fois par semaine');
        $this->assertSelectorExists('input[name="consent"][required]');

        // Sans la case cochée : rien n'est créé ni envoyé
        $this->createCommitment(['target' => '', 'freeLabel' => 'Courir', 'connectionId' => 0, 'partnerName' => 'Paul', 'partnerEmail' => 'paul@exemple.org']);
        $this->assertResponseRedirects('/social/redevabilite/nouveau');
        $this->assertCount(0, $this->em()->getRepository(AccountabilityCommitment::class)->findBy(['owner' => $owner->getId()]));
    }

    public function testFullJourneyWithAPartnerWithoutAccount(): void
    {
        $owner = $this->createUser();
        $habit = (new Habit())->setTitle('Prier 10 minutes le matin')->setUser($owner);
        $this->em()->persist($habit);
        $this->em()->flush();
        $partnerEmail = 'mentor-'.uniqid().'@exemple.org';

        $this->client->loginUser($owner);
        $this->createCommitment(['target' => 'HABIT:'.$habit->getId(), 'why' => 'Rester enraciné', 'connectionId' => 0, 'partnerName' => 'Samuel', 'partnerEmail' => $partnerEmail, 'consent' => '1']);
        $this->assertResponseRedirects('/social/redevabilite');

        $c = $this->em()->getRepository(AccountabilityCommitment::class)->findOneBy(['owner' => $owner->getId()]);
        $this->assertSame('Prier 10 minutes le matin', $c->getTargetLabel());
        $this->assertTrue($c->isPending());
        $invitation = $this->emailsTo($partnerEmail);
        $this->assertCount(1, $invitation);
        $this->assertStringContainsString('/redevabilite/'.$c->getPartnerToken(), (string) $invitation[0]->getHtmlBody() ?: '');

        // Pas d'interpellation tant que le partenaire n'a pas accepté
        $this->client->request('POST', '/social/redevabilite/'.$c->getId().'/interpeller', ['_csrf_token' => $this->token('/social/redevabilite')]);
        $this->assertCount(0, $this->getMailerMessages());
        $this->assertCount(0, $this->em()->getRepository(AccountabilityNudge::class)->findBy(['commitment' => $c->getId()]));

        // Le partenaire accepte depuis son lien, sans compte
        $this->client->getCookieJar()->clear();
        $this->client->restart();
        $crawler = $this->client->request('GET', '/redevabilite/'.$c->getPartnerToken());
        $this->assertSelectorTextContains('h1', 'compte sur vous');
        $this->client->submit($crawler->selectButton('J\'accepte d\'être son partenaire')->form());
        $this->em()->clear();
        $c = $this->em()->find(AccountabilityCommitment::class, $c->getId());
        $this->assertTrue($c->isActive());

        // La personne demande à être interpellée : e-mail au partenaire et copie à la personne
        $this->client->loginUser($this->findUser((string) $owner->getEmail()));
        $this->client->request('POST', '/social/redevabilite/'.$c->getId().'/interpeller', ['_csrf_token' => $this->token('/social/redevabilite'), 'message' => 'Semaine difficile']);
        $this->assertResponseRedirects();
        $nudgeMail = $this->emailsTo($partnerEmail);
        $copy = $this->emailsTo((string) $owner->getEmail());
        $this->assertNotEmpty($nudgeMail);
        $this->assertNotEmpty($copy, 'La personne reçoit une copie');
        $this->assertStringContainsString('Semaine difficile', (string) end($copy)->getHtmlBody());

        // Une seule interpellation par période
        $this->client->request('POST', '/social/redevabilite/'.$c->getId().'/interpeller', ['_csrf_token' => $this->token('/social/redevabilite')]);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('main', 'Prochaine interpellation possible');
        $this->assertCount(1, $this->em()->getRepository(AccountabilityNudge::class)->findBy(['commitment' => $c->getId()]));

        // Le partenaire répond en un clic (le lien de l'e-mail présélectionne, il confirme)
        $nudge = $this->em()->getRepository(AccountabilityNudge::class)->findOneBy(['commitment' => $c->getId()]);
        $this->client->restart();
        $crawler = $this->client->request('GET', '/redevabilite/reponse/'.$nudge->getToken().'?action=CALLED');
        $this->assertNull($this->em()->find(AccountabilityNudge::class, $nudge->getId())->getRespondedAt(), 'Un simple clic sur le lien ne vaut pas réponse');
        $form = $crawler->selectButton('Envoyer ma réponse')->form();
        $this->client->submit($form, ['note' => 'Bon échange ce soir']);
        $this->em()->clear();
        $nudge = $this->em()->find(AccountabilityNudge::class, $nudge->getId());
        $this->assertSame('CALLED', $nudge->getResponseAction());

        $notes = $this->em()->getRepository(Notification::class)->findBy(['user' => $owner->getId()]);
        $this->assertTrue((bool) array_filter($notes, fn ($n) => str_contains((string) $n->getMessage(), 'Bon échange ce soir')));

        // Chiffres et historique visibles
        $this->client->loginUser($this->findUser((string) $owner->getEmail()));
        $this->client->request('GET', '/social/redevabilite');
        $this->assertSelectorTextContains('#engagement-'.$c->getId(), '100 %');
        $this->assertSelectorTextContains('#engagement-'.$c->getId(), 'Je l\'ai appelé(e)');
    }

    public function testPartnerCanStopAndDeclineAndNobodyElseCanActOnACommitment(): void
    {
        $owner = $this->createUser();
        $this->client->loginUser($owner);
        $email = 'ami-'.uniqid().'@exemple.org';
        $this->createCommitment(['target' => '', 'freeLabel' => 'Lire la Bible chaque jour', 'connectionId' => 0, 'partnerName' => 'Ami', 'partnerEmail' => $email, 'consent' => '1']);
        $c = $this->em()->getRepository(AccountabilityCommitment::class)->findOneBy(['owner' => $owner->getId()]);

        // Un autre utilisateur ne peut ni interpeller ni arrêter
        $intruder = $this->createUser();
        $this->client->loginUser($intruder);
        $this->client->request('POST', '/social/redevabilite/'.$c->getId().'/stop', ['_csrf_token' => $this->token('/social/redevabilite')]);
        $this->assertResponseStatusCodeSame(404);

        // Le partenaire décline : l'engagement s'arrête et la personne est prévenue
        $this->client->restart();
        $crawler = $this->client->request('GET', '/redevabilite/'.$c->getPartnerToken());
        $this->client->submit($crawler->selectButton('Je ne peux pas')->form());
        $this->em()->clear();
        $this->assertSame(AccountabilityCommitment::STATUS_DECLINED, $this->em()->find(AccountabilityCommitment::class, $c->getId())->getStatus());

        $this->client->request('GET', '/redevabilite/'.str_repeat('a', 48));
        $this->assertResponseStatusCodeSame(404);
    }

    public function testFreeOfferAllowsOneOpenCommitmentAndAcceptedPartnerCanBeChosen(): void
    {
        $owner = $this->createUser();
        $friend = $this->createUser();
        $connection = (new Connection())->setRequester($owner)->setReceiver($friend)->setStatus('ACCEPTED');
        $this->em()->persist($connection);
        $this->em()->flush();

        $this->client->loginUser($owner);
        $this->createCommitment(['target' => '', 'freeLabel' => 'Épargner 10 %', 'connectionId' => $connection->getId(), 'consent' => '1']);
        $c = $this->em()->getRepository(AccountabilityCommitment::class)->findOneBy(['owner' => $owner->getId()]);
        $this->assertSame($friend->getId(), $c->getPartnerUser()?->getId());
        $this->assertSame($friend->getEmail(), $c->getPartnerEmail());

        $this->client->request('GET', '/social/redevabilite/nouveau');
        $this->assertResponseRedirects('/social/redevabilite');
        $this->createCommitment(['target' => '', 'freeLabel' => 'Autre', 'connectionId' => $connection->getId(), 'consent' => '1']);
        $this->assertCount(1, $this->em()->getRepository(AccountabilityCommitment::class)->findBy(['owner' => $owner->getId()]));

        // Le partenaire inscrit voit son rôle dans sa propre page
        $this->em()->find(AccountabilityCommitment::class, $c->getId())->accept();
        $this->em()->flush();
        $this->client->loginUser($friend);
        $this->client->request('GET', '/social/redevabilite');
        $this->assertSelectorTextContains('main', 'Je suis partenaire de redevabilité');
        $this->assertSelectorTextContains('main', 'Épargner 10 %');
    }
}
