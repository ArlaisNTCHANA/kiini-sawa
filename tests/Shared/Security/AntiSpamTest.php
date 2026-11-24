<?php

namespace App\Tests\Shared\Security;

use App\Abonnement\Service\Entitlements;
use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Feedback\Service\FeedbackService;
use App\Organisation\Service\OrganisationService;
use App\Shared\Security\RateLimits;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Audit de sécurité, lot 2 : anti-spam e-mail et limitation de débit. */
class AntiSpamTest extends WebTestCase
{
    use AccountTestTrait;

    private function token(KernelBrowser $client, string $url, string $selector): string
    {
        return $client->request('GET', $url)->filter($selector.' input[name="_csrf_token"]')->first()->attr('value');
    }

    private function org(bool $licensed): array
    {
        $owner = $this->createUser();
        $org = static::getContainer()->get(OrganisationService::class)->create($owner, 'Église '.uniqid(), 'EGLISE');
        if ($licensed) {
            $org->setSeats(10)->setSeatsValidUntil(new \DateTimeImmutable('+6 months'));
        }
        $this->em()->flush();

        return [$org->getId(), $owner];
    }

    private function emails(int $n, string $prefix = 'invite'): string
    {
        return implode("\n", array_map(fn ($i) => $prefix.$i.'-'.bin2hex(random_bytes(3)).'@example.com', range(1, $n)));
    }

    // ── Invitations d'organisation ───────────────────────────────────────────

    public function testAnOrganisationWithoutLicenceCannotSendMoreThanFiveInvitationsADay(): void
    {
        $client = static::createClient();
        [$orgId, $owner] = $this->org(false);
        $client->loginUser($owner);
        $token = $this->token($client, '/organisation?org='.$orgId, 'form[action$="/invitations"]');

        $client->request('POST', "/organisation/$orgId/invitations", ['_csrf_token' => $token, 'emails' => $this->emails(5)]);
        $this->assertQueuedEmailCount(5);

        $client->request('POST', "/organisation/$orgId/invitations", ['_csrf_token' => $token, 'emails' => $this->emails(1)]);
        $this->assertResponseRedirects();
        $this->assertQueuedEmailCount(0);
        $flash = $client->getRequest()->getSession()->getFlashBag()->peekAll();
        $this->assertStringContainsString('Limite quotidienne', implode(' ', $flash['error'] ?? []));
    }

    public function testAMassInvitationWithoutLicenceIsRefusedAsAWhole(): void
    {
        $client = static::createClient();
        [$orgId, $owner] = $this->org(false);
        $client->loginUser($owner);
        $token = $this->token($client, '/organisation?org='.$orgId, 'form[action$="/invitations"]');
        $client->request('POST', "/organisation/$orgId/invitations", ['_csrf_token' => $token, 'emails' => $this->emails(50)]);
        $this->assertQueuedEmailCount(0);
    }

    public function testALicensedOrganisationHasAHigherCeiling(): void
    {
        $client = static::createClient();
        [$orgId, $owner] = $this->org(true);
        $client->loginUser($owner);
        $token = $this->token($client, '/organisation?org='.$orgId, 'form[action$="/invitations"]');
        $client->request('POST', "/organisation/$orgId/invitations", ['_csrf_token' => $token, 'emails' => $this->emails(40)]);
        $this->assertQueuedEmailCount(40);
    }

    // ── Couple ───────────────────────────────────────────────────────────────

    public function testCoupleInvitationsAreLimitedPerAccount(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);
        $limits = static::getContainer()->get(RateLimits::class);
        for ($i = 0; $i < 5; ++$i) {
            $this->assertTrue($limits->allow('couple_create', (string) $user->getId()));
        }
        $token = $this->token($client, '/couple', 'form[action$="/couple/creer"]');
        $client->request('POST', '/couple/creer', ['_csrf_token' => $token, 'email' => 'conjoint'.uniqid().'@example.com']);
        $this->assertQueuedEmailCount(0);
        $this->assertStringContainsString('Trop de tentatives', implode(' ', $client->getRequest()->getSession()->getFlashBag()->peekAll()['error'] ?? []));
    }

    // ── Plan envoyé par e-mail ───────────────────────────────────────────────

    public function testSendingAPlanByEmailIsLimitedToFiveEveryTenMinutes(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $client->loginUser($user);
        $codes = [];
        for ($i = 0; $i < 7; ++$i) {
            $client->request('POST', '/plan-de-vie/email', server: ['HTTP_ORIGIN' => 'http://localhost', 'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], content: '{}');
            $codes[] = $client->getResponse()->getStatusCode();
        }
        $this->assertContains(429, $codes);
        $this->assertSame(429, end($codes));
        $this->assertLessThanOrEqual(5, \count(array_filter($codes, fn ($c) => 429 !== $c)), 'au plus 5 envois acceptés');
    }

    // ── Mot de passe actuel : anti brute-force ──────────────────────────────

    public function testTheCurrentPasswordCannotBeGuessedByBruteForce(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $token = $this->token($client, '/profil', 'form[action$="/profil/mot-de-passe"]');
        $messages = [];
        for ($i = 1; $i <= 7; ++$i) {
            $client->request('POST', '/profil/mot-de-passe', ['_csrf_token' => $token, 'currentPassword' => 'essai-'.$i, 'newPassword' => 'Nouveau-mot-de-passe-2026!']);
            $messages[] = implode(' ', $client->getRequest()->getSession()->getFlashBag()->all()['error'] ?? []);
        }
        $this->assertStringContainsString('Mot de passe actuel incorrect', $messages[0]);
        $this->assertStringContainsString('Mot de passe actuel incorrect', $messages[4]);
        $this->assertStringContainsString('Trop de tentatives', $messages[5], 'dès la 6e tentative : pause');
        $this->assertStringContainsString('Trop de tentatives', $messages[6]);
        // Même le bon mot de passe est refusé pendant la pause : on ne peut pas deviner puis réessayer
        $client->request('POST', '/profil/mot-de-passe', ['_csrf_token' => $token, 'currentPassword' => 'mot-de-passe-solide', 'newPassword' => 'Nouveau-mot-de-passe-2026!']);
        $this->assertStringContainsString('Trop de tentatives', implode(' ', $client->getRequest()->getSession()->getFlashBag()->all()['error'] ?? []));
    }

    public function testEmailChangeAndAccountDeletionShareTheSameBruteForceProtection(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $limits = static::getContainer()->get(RateLimits::class);
        for ($i = 0; $i < 5; ++$i) {
            $limits->allow('account_security', (string) $user->getId());
        }
        $emailToken = $this->token($client, '/profil', 'form[action$="/profil/email"]');
        $client->request('POST', '/profil/email', ['_csrf_token' => $emailToken, 'currentPassword' => 'mot-de-passe-solide', 'newEmail' => 'nouveau'.uniqid().'@example.com']);
        $this->assertStringContainsString('Trop de tentatives', implode(' ', $client->getRequest()->getSession()->getFlashBag()->all()['error'] ?? []));
        $this->assertQueuedEmailCount(0);

        $deleteToken = $this->token($client, '/profil', 'form[action$="/profil/supprimer"]');
        $client->request('POST', '/profil/supprimer', ['_csrf_token' => $deleteToken, 'confirmation' => 'SUPPRIMER', 'currentPassword' => 'mot-de-passe-solide']);
        $this->assertNotNull($this->findUser($user->getEmail()), 'le compte n\'est pas supprimé pendant la pause');
    }

    // ── Formulaires publics ──────────────────────────────────────────────────

    public function testPublicFeedbackFormIsRateLimitedPerLink(): void
    {
        $client = static::createClient();
        $owner = $this->createUser(pro: true);
        $request = static::getContainer()->get(FeedbackService::class)->open($owner, false);
        $url = '/avis/'.$request->getToken();
        $statuses = [];
        $ip = '203.0.113.'.random_int(1, 254); // adresse propre à ce test : les compteurs ne se mélangent pas d'une exécution à l'autre
        for ($i = 0; $i < 12; ++$i) {
            $client->request('POST', $url, ['website' => 'robot'], server: ['REMOTE_ADDR' => $ip]);
            $statuses[] = $client->getResponse()->getStatusCode();
        }
        $this->assertSame(302, $statuses[0], 'le robot est renvoyé vers le remerciement, sans rien enregistrer');
        $this->assertSame(429, $statuses[11], 'au-delà de 10 envois en 10 minutes pour un même lien : 429');
        $this->assertSame(10, \count(array_filter($statuses, fn ($c) => 302 === $c)));
    }

    public function testTheLimiterIsPerKey(): void
    {
        static::createClient();
        $limits = static::getContainer()->get(RateLimits::class);
        $a = 'cle-a-'.uniqid();
        for ($i = 0; $i < 10; ++$i) {
            $this->assertTrue($limits->allow('public_form', $a));
        }
        $this->assertFalse($limits->allow('public_form', $a));
        $this->assertTrue($limits->allow('public_form', 'cle-b-'.uniqid()), 'une autre clé n\'est pas touchée');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException::class);
        $limits->require('public_form', $a);
    }
}
