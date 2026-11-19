<?php

namespace App\Tests\Shared;

use App\Organisation\Service\OrganisationService;
use App\Shared\Twig\JsonSafeExtension;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Dotenv\Dotenv;

/** Contre-vérification de l'audit de sécurité : quatre derniers détails. */
class FinalChecksTest extends WebTestCase
{
    use AccountTestTrait;

    private function token(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $url, string $selector): string
    {
        return $client->request('GET', $url)->filter($selector.' input[name="_csrf_token"]')->first()->attr('value');
    }

    // ── 1. Invitations : plafond par compte, création d'organisations limitée ──

    public function testSeveralFreeOrganisationsDoNotMultiplyTheDailyInvitations(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $service = static::getContainer()->get(OrganisationService::class);
        $a = $service->create($owner, 'Église A '.uniqid(), 'EGLISE');
        $b = $service->create($owner, 'Église B '.uniqid(), 'EGLISE');
        $this->em()->flush();
        $client->loginUser($owner);

        $mails = fn (int $n) => implode("\n", array_map(fn ($i) => "x$i-".bin2hex(random_bytes(3)).'@example.com', range(1, $n)));
        $tokenA = $this->token($client, '/organisation?org='.$a->getId(), 'form[action$="/invitations"]');
        $client->request('POST', '/organisation/'.$a->getId().'/invitations', ['_csrf_token' => $tokenA, 'emails' => $mails(5)]);
        $this->assertQueuedEmailCount(5);

        $tokenB = $this->token($client, '/organisation?org='.$b->getId(), 'form[action$="/invitations"]');
        $client->request('POST', '/organisation/'.$b->getId().'/invitations', ['_csrf_token' => $tokenB, 'emails' => $mails(1)]);
        $this->assertQueuedEmailCount(0);
        $this->assertStringContainsString('Limite quotidienne', implode(' ', $client->getRequest()->getSession()->getFlashBag()->peekAll()['error'] ?? []), 'la 2e organisation du même compte partage le plafond');
    }

    public function testALicensedOrganisationStillHasItsOwnCeiling(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $org = static::getContainer()->get(OrganisationService::class)->create($owner, 'Église '.uniqid(), 'EGLISE');
        $org->setSeats(10)->setSeatsValidUntil(new \DateTimeImmutable('+6 months'));
        $this->em()->flush();
        $client->loginUser($owner);
        $token = $this->token($client, '/organisation?org='.$org->getId(), 'form[action$="/invitations"]');
        $client->request('POST', '/organisation/'.$org->getId().'/invitations', ['_csrf_token' => $token, 'emails' => implode("\n", array_map(fn ($i) => "l$i-".bin2hex(random_bytes(3)).'@example.com', range(1, 30)))]);
        $this->assertQueuedEmailCount(30);
    }

    public function testAccountsCanCreateOnlyTwoOrganisationsADay(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $count = fn () => $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM membership WHERE user_id = ?', [$user->getId()]);
        for ($i = 1; $i <= 3; ++$i) {
            $client->request('POST', '/organisation/creer', ['_csrf_token' => 'jeton-de-test', 'name' => 'Organisation '.$i.' '.uniqid(), 'type' => 'EGLISE']);
        }
        $this->assertSame(2, (int) $count(), 'la troisième création du jour est refusée');
        $this->assertStringContainsString('plusieurs organisations', implode(' ', $client->getRequest()->getSession()->getFlashBag()->peekAll()['error'] ?? []));
    }

    // ── 2. json_safe : séparateurs de ligne et indicateurs ─────────────────────

    public function testLineSeparatorsAreWrittenAsLiteralEscapeSequences(): void
    {
        $out = (string) JsonSafeExtension::encode("a\u{2028}b\u{2029}c");
        $this->assertSame('"a\u2028b\u2029c"', $out, 'séquences littérales \\u2028 et \\u2029');
        $this->assertStringNotContainsString("\u{2028}", $out);
        $this->assertStringNotContainsString("\u{2029}", $out);
        $this->assertSame("a\u{2028}b\u{2029}c", json_decode($out), 'et le JSON reste fidèle');
        $this->assertStringContainsString('\\\\u2028', (string) file_get_contents(\dirname(__DIR__, 2).'/src/Shared/Twig/JsonSafeExtension.php'), 'plus de caractère brut dans le code source');
        $this->assertDoesNotMatchRegularExpression('/\x{2028}|\x{2029}/u', (string) file_get_contents(\dirname(__DIR__, 2).'/src/Shared/Twig/JsonSafeExtension.php'));
    }

    public function testFlagsNoLongerConflictAndInvalidUtf8IsCleaned(): void
    {
        $this->assertSame(0, JsonSafeExtension::FLAGS & \JSON_PARTIAL_OUTPUT_ON_ERROR);
        $this->assertNotSame(0, JsonSafeExtension::FLAGS & \JSON_THROW_ON_ERROR);
        $out = (string) JsonSafeExtension::encode("Caf\xE9 \xFF");
        $this->assertJson($out, 'un texte mal encodé ne fait pas échouer la page');
        $this->assertStringContainsString("\u{FFFD}", json_decode($out));
        $this->expectException(\JsonException::class);
        JsonSafeExtension::encode(NAN);
    }

    // ── 3. Écran de vérification « leurre » : mêmes contrôles qu'un vrai compte ──

    private function register(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): void
    {
        $form = $client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Test', 'email' => $email, 'password' => 'une phrase assez longue']);
        $client->followRedirect();
    }

    /** @return list<string> messages affichés après chaque action sur l'écran de vérification */
    private function screenBehaviour(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        $out = [];
        foreach ([['/verification-email', ['_csrf_token' => 'faux', 'code' => '123456']], ['/verification-email/renvoyer', ['_csrf_token' => 'faux']], ['/verification-email/renvoyer', ['_csrf_token' => 'jeton-de-test']], ['/verification-email/renvoyer', ['_csrf_token' => 'jeton-de-test']], ['/verification-email', ['_csrf_token' => 'jeton-de-test', 'code' => '000000']]] as [$url, $body]) {
            $client->request('POST', $url, $body);
            $text = $client->followRedirect()->filter('body')->text();
            $seen = 'aucun message';
            foreach (['La page a expiré', 'Code incorrect ou expiré', 'Patientez une minute', 'Un nouveau code vous a été envoyé'] as $message) {
                if (str_contains($text, $message)) {
                    $seen = $message;
                    break;
                }
            }
            $out[] = $seen;
        }

        return $out;
    }

    public function testTheDecoyScreenBehavesExactlyLikeARealSignup(): void
    {
        $client = static::createClient();
        $existing = $this->createUser();
        $this->register($client, $existing->getEmail());
        $decoy = $this->screenBehaviour($client);

        $client->restart();
        $this->register($client, $this->uniqueEmail('nouveau'));
        $real = $this->screenBehaviour($client);

        $this->assertSame($real, $decoy, 'jeton invalide, renvoi immédiat, renvoi répété, code faux : mêmes messages');
        $this->assertSame('La page a expiré', $decoy[0]);
        $this->assertSame('La page a expiré', $decoy[1]);
        $this->assertSame('Patientez une minute', $decoy[2], 'renvoi tout de suite après l\'inscription : délai de 60 s');
        $this->assertSame('Patientez une minute', $decoy[3]);
        $this->assertSame('Code incorrect ou expiré', $decoy[4]);
    }

    // ── 4. TRUSTED_HOSTS de production ────────────────────────────────────────

    public function testProductionAcceptsOnlyTheSiteHosts(): void
    {
        $root = \dirname(__DIR__, 2);
        $prod = (new Dotenv())->parse((string) file_get_contents($root.'/.env.prod'));
        $this->assertArrayHasKey('TRUSTED_HOSTS', $prod);
        $pattern = '{'.$prod['TRUSTED_HOSTS'].'}i';
        foreach (['kiinisawa.com', 'www.kiinisawa.com'] as $host) {
            $this->assertSame(1, preg_match($pattern, $host), $host);
        }
        foreach (['localhost', '127.0.0.1', 'app.kiinisawa.com', 'evil.example', 'kiinisawa.com.evil.example', 'xkiinisawa.com', '194.36.184.161'] as $host) {
            $this->assertSame(0, preg_match($pattern, $host), $host.' doit être refusé en production');
        }
        // Le développement et les tests gardent localhost (fichier .env)
        $dev = (new Dotenv())->parse((string) file_get_contents($root.'/.env'));
        $this->assertSame(1, preg_match('{'.$dev['TRUSTED_HOSTS'].'}i', 'localhost'));
    }
}
