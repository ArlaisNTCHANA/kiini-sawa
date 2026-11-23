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
        $this->assertSelectorTextContains('main', 'vous recevrez une copie');
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

    /** Engagement actif sur un rituel, accepté il y a $daysAgo jours (dates reculées en SQL). */
    private function activeHabitCommitment(int $daysAgo, ?int $habitDays = 3, ?int $inactiveDays = 7): array
    {
        $owner = $this->createUser();
        $habit = (new Habit())->setTitle('Lire la Bible chaque matin')->setUser($owner);
        $this->em()->persist($habit);
        $this->em()->flush();
        $c = new AccountabilityCommitment($owner, 'Samuel', 'samuel-'.uniqid().'@exemple.org', AccountabilityCommitment::TARGET_HABIT, $habit->getId(), 'Lire la Bible chaque matin', null);
        $c->setAlerts($habitDays, null, $inactiveDays);
        $c->accept();
        $this->em()->persist($c);
        $this->em()->flush();
        $date = (new \DateTimeImmutable("-$daysAgo days"))->format('Y-m-d H:i:s');
        $conn = $this->em()->getConnection();
        $conn->executeStatement('UPDATE accountability_commitment SET partner_accepted_at = ?, created_at = ? WHERE id = ?', [$date, $date, $c->getId()]);
        $conn->executeStatement('UPDATE `user` SET last_active_at = ? WHERE id = ?', [(new \DateTimeImmutable())->format('Y-m-d H:i:s'), $owner->getId()]);
        $this->em()->clear();

        return [$this->em()->find(AccountabilityCommitment::class, $c->getId()), $habit->getId()];
    }

    private function service(): \App\Social\Service\AccountabilityService
    {
        return static::getContainer()->get(\App\Social\Service\AccountabilityService::class);
    }

    public function testPartnerIsAlertedAutomaticallyWhenTheRitualIsMissedAndOnlyOncePerSlip(): void
    {
        [$c, $habitId] = $this->activeHabitCommitment(5);

        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        $nudges = $this->em()->getRepository(AccountabilityNudge::class)->findBy(['commitment' => $c->getId()]);
        $this->assertCount(1, $nudges);
        $this->assertSame(AccountabilityNudge::REASON_HABIT_MISSED, $nudges[0]->getReason());
        $this->assertStringContainsString('depuis 5 jours', (string) $nudges[0]->getFact());
        $partnerMail = $this->emailsTo($c->getPartnerEmail());
        $this->assertNotEmpty($partnerMail);
        $this->assertStringContainsString('a peut-être besoin de vous', (string) $partnerMail[0]->getSubject());
        $this->assertNotEmpty($this->emailsTo((string) $c->getOwner()->getEmail()), 'Copie à la personne');

        // Le même décrochage ne redéclenche pas d'alerte, même après le délai de 7 jours
        $this->em()->getConnection()->executeStatement('UPDATE accountability_nudge SET sent_at = ? WHERE id = ?', [(new \DateTimeImmutable('-8 days'))->format('Y-m-d H:i:s'), $nudges[0]->getId()]);
        $this->em()->getConnection()->executeStatement('UPDATE accountability_commitment SET partner_accepted_at = ? WHERE id = ?', [(new \DateTimeImmutable('-13 days'))->format('Y-m-d H:i:s'), $c->getId()]);
        $this->em()->clear();
        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        $this->assertCount(1, $this->em()->getRepository(AccountabilityNudge::class)->findBy(['commitment' => $c->getId()]));
    }

    public function testNoAlertBeforeTheThresholdOrWhenPausedOrDisabled(): void
    {
        [$recent] = $this->activeHabitCommitment(2);
        [$paused] = $this->activeHabitCommitment(10);
        $paused->pause();
        [$disabled] = $this->activeHabitCommitment(10, null, null);
        $this->em()->flush();

        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        foreach ([$recent, $paused, $disabled] as $c) {
            $this->assertCount(0, $this->em()->getRepository(AccountabilityNudge::class)->findBy(['commitment' => $c->getId()]));
        }
    }

    public function testInactivityAlertAndResumptionWithin72Hours(): void
    {
        [$c, $habitId] = $this->activeHabitCommitment(10, null, 7);
        $this->em()->getConnection()->executeStatement('UPDATE `user` SET last_active_at = ? WHERE id = ?', [(new \DateTimeImmutable('-9 days'))->format('Y-m-d H:i:s'), $c->getOwner()->getId()]);
        $this->em()->clear();

        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        $nudge = $this->em()->getRepository(AccountabilityNudge::class)->findOneBy(['commitment' => $c->getId()]);
        $this->assertSame(AccountabilityNudge::REASON_INACTIVE, $nudge->getReason());
        $this->assertStringContainsString('ne s\'est pas connecté(e)', (string) $nudge->getFact());

        // Le lendemain de l'alerte, la personne valide son rituel : reprise comptée
        $this->em()->getConnection()->executeStatement('UPDATE accountability_nudge SET sent_at = ? WHERE id = ?', [(new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'), $nudge->getId()]);
        $log = (new \App\Rituels\Entity\HabitLog())->setHabit($this->em()->find(Habit::class, $habitId))->setDate(new \DateTime('today'))->setStatus(true);
        $this->em()->persist($log);
        $this->em()->flush();
        $this->em()->clear();
        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        $this->assertNotNull($this->em()->find(AccountabilityNudge::class, $nudge->getId())->getResumedAt());
        $this->assertSame(1, $this->service()->stats($this->em()->find(AccountabilityCommitment::class, $c->getId()))['resumed']);
    }

    public function testChosenAlertsAreSavedFromTheFormAndShownInThePreview(): void
    {
        $owner = $this->createUser();
        $habit = (new Habit())->setTitle('Marcher 30 minutes')->setUser($owner);
        $this->em()->persist($habit);
        $this->em()->flush();
        $this->client->loginUser($owner);
        $fields = ['target' => 'HABIT:'.$habit->getId(), 'connectionId' => 0, 'partnerName' => 'Ruth', 'partnerEmail' => 'ruth-'.uniqid().'@exemple.org',
            'alertsForm' => '1', 'alertHabit' => '1', 'alertHabitDays' => 4, 'alertInactiveDays' => 7, '_csrf_token' => $this->token()];
        $this->client->request('POST', '/social/redevabilite/apercu', $fields);
        $this->assertSelectorTextContains('main', 'Sans que vous ayez à le demander');
        $this->assertSelectorTextContains('main', '4 jours de suite');
        $this->assertSelectorTextNotContains('main', 'vous ne vous connectez plus', 'Case « inactivité » décochée');
        $this->assertSelectorTextContains('main', 'Exemple d\'alerte automatique');

        unset($fields['alertsForm'], $fields['alertHabit']);
        $this->client->request('POST', '/social/redevabilite/creer', ['alertHabitDays' => 4, 'alertInactiveDays' => 0, 'consent' => '1'] + $fields);
        $c = $this->em()->getRepository(AccountabilityCommitment::class)->findOneBy(['owner' => $owner->getId()]);
        $this->assertSame(4, $c->getAlertHabitDays());
        $this->assertNull($c->getAlertInactiveDays());
        $this->assertNull($c->getAlertGoalDays(), 'Pas d\'alerte « objectif » sur un rituel');
    }

    public function testWeeklyReviewShowsTheWeekOfAccountability(): void
    {
        [$c] = $this->activeHabitCommitment(5);
        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        $nudge = $this->em()->getRepository(AccountabilityNudge::class)->findOneBy(['commitment' => $c->getId()]);
        $this->service()->respond($nudge, 'CALLED', 'On a parlé ce soir');

        $this->client->loginUser($this->findUser((string) $c->getOwner()->getEmail()));
        $this->client->request('GET', '/bilan/revue-hebdomadaire');
        $this->assertSelectorTextContains('#redevabilite', 'Ma redevabilité cette semaine');
        $this->assertSelectorTextContains('#redevabilite', '1 alerte');
        $this->assertSelectorTextContains('#redevabilite', 'Je l\'ai appelé(e)');
        $this->assertSelectorTextContains('#redevabilite', 'On a parlé ce soir');

        // Sans engagement : pas d'encart
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/bilan/revue-hebdomadaire');
        $this->assertSelectorNotExists('#redevabilite');
    }

    /** @return array{status: int, json: array<string, mixed>} */
    private function api(string $method, string $path, ?array $payload, string $bearer): array
    {
        $this->client->request($method, $path, server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$bearer], content: null !== $payload ? json_encode($payload) : null);

        return ['status' => $this->client->getResponse()->getStatusCode(), 'json' => json_decode((string) $this->client->getResponse()->getContent(), true) ?? []];
    }

    public function testMobileApiListsCommitmentsAndSendsHelpWithCopy(): void
    {
        [$c] = $this->activeHabitCommitment(1);
        $owner = $this->findUser((string) $c->getOwner()->getEmail());
        $token = static::getContainer()->get(\App\Mobile\Service\MobileTokenService::class)->issue($owner, 'Test')['accessToken'];
        $this->client->restart();

        $list = $this->api('GET', '/api/mobile/v1/redevabilite', null, $token);
        $this->assertSame(200, $list['status']);
        $this->assertSame('Lire la Bible chaque matin', $list['json']['commitments'][0]['targetLabel']);
        $this->assertSame(3, $list['json']['commitments'][0]['alerts']['habitDays']);
        $this->assertStringContainsString('/social/redevabilite/nouveau', $list['json']['createUrl']);

        $help = $this->api('POST', '/api/mobile/v1/redevabilite/'.$c->getId().'/coup-de-main', ['message' => 'Dur cette semaine'], $token);
        $this->assertSame(200, $help['status']);
        $this->assertSame(1, $help['json']['commitment']['stats']['messages']);
        $this->assertNotEmpty($this->emailsTo((string) $owner->getEmail()), 'Copie à la personne');
        $this->assertSame(409, $this->api('POST', '/api/mobile/v1/redevabilite/'.$c->getId().'/coup-de-main', [], $token)['status'], 'Au plus un message tous les 7 jours');

        $this->assertSame('PAUSED', $this->api('POST', '/api/mobile/v1/redevabilite/'.$c->getId().'/pause', [], $token)['json']['commitment']['status']);

        // Un autre compte ne voit ni ne touche cet engagement
        $other = static::getContainer()->get(\App\Mobile\Service\MobileTokenService::class)->issue($this->createUser(), 'Autre')['accessToken'];
        $this->assertSame(404, $this->api('POST', '/api/mobile/v1/redevabilite/'.$c->getId().'/arreter', [], $other)['status']);
        $this->assertSame([], $this->api('GET', '/api/mobile/v1/redevabilite', null, $other)['json']['commitments']);
    }

    public function testDeletedRitualPausesTheCommitmentWithoutAlertingThePartner(): void
    {
        [$c, $habitId] = $this->activeHabitCommitment(10);
        $this->em()->getConnection()->executeStatement('DELETE FROM habit WHERE id = ?', [$habitId]);
        $this->em()->clear();

        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        $this->assertCount(0, $this->em()->getRepository(AccountabilityNudge::class)->findBy(['commitment' => $c->getId()]), 'Aucune fausse alerte');
        $this->assertCount(0, $this->emailsTo($c->getPartnerEmail()));
        $c = $this->em()->find(AccountabilityCommitment::class, $c->getId());
        $this->assertTrue($c->isPaused());
        $this->assertSame(AccountabilityCommitment::TARGET_FREE, $c->getTargetType(), 'Devenu un engagement libre');
        $this->assertNull($c->getAlertHabitDays());
        $this->assertSame(7, $c->getAlertInactiveDays(), 'L\'alerte « plus de connexion » reste');
        $notes = $this->em()->getRepository(Notification::class)->findBy(['user' => $c->getOwner()->getId()]);
        $this->assertTrue((bool) array_filter($notes, fn ($n) => str_contains((string) $n->getMessage(), 'n\'existe plus')));

        // Repris comme engagement libre : il n'est plus remis en pause
        $this->service()->ownerAction($c, $c->getOwner(), 'resume');
        $this->service()->runDailyChecks(new \DateTimeImmutable('today'));
        $this->assertTrue($this->em()->find(AccountabilityCommitment::class, $c->getId())->isActive());
    }

    public function testInvitationsAreLimitedPerDayEvenForPro(): void
    {
        $owner = $this->createUser(pro: true);
        $this->client->loginUser($owner);
        for ($i = 1; $i <= 4; ++$i) {
            $this->createCommitment(['target' => '', 'freeLabel' => 'Engagement '.$i, 'connectionId' => 0, 'partnerName' => 'Ami', 'partnerEmail' => 'ami'.$i.'-'.uniqid().'@exemple.org', 'consent' => '1']);
        }
        $this->assertCount(3, $this->em()->getRepository(AccountabilityCommitment::class)->findBy(['owner' => $owner->getId()]));
        $this->client->followRedirect();
        $this->assertSelectorTextContains('main', 'plusieurs invitations aujourd\'hui');
    }

    public function testAfterLoginTheCreationFormOpensDirectly(): void
    {
        $owner = $this->createUser();
        $this->client->request('GET', '/social/redevabilite/nouveau');
        $this->assertResponseRedirects('/login');
        $crawler = $this->client->followRedirect();
        $form = $crawler->filter('form')->form(['email' => $owner->getEmail(), 'password' => 'mot-de-passe-solide']);
        $this->client->submit($form);
        $this->assertResponseRedirects('/social/redevabilite/nouveau');
    }
}
