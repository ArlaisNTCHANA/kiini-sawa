<?php

namespace App\Tests\Mobile;

use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Date de naissance depuis l'application : lue et modifiée par PATCH /moi (mêmes règles que le site), invitation
 * de l'accueil écartée une fois pour toutes, interrupteur du message d'anniversaire dans les préférences.
 */
final class MobileBirthDateTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    /** @return array{status: int, json: array<string, mixed>} */
    private function api(string $method, string $path, ?array $payload = null, ?string $bearer = null): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if (null !== $bearer) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }
        $this->client->request($method, $path, server: $headers, content: null !== $payload ? json_encode($payload, \JSON_THROW_ON_ERROR) : null);
        $json = json_decode((string) $this->client->getResponse()->getContent(), true);

        return ['status' => $this->client->getResponse()->getStatusCode(), 'json' => \is_array($json) ? $json : []];
    }

    public function testDateOfBirthFromThePhone(): void
    {
        $user = $this->createUser();
        $token = $this->api('POST', '/api/mobile/v1/connexion', ['email' => $user->getEmail(), 'password' => 'mot-de-passe-solide', 'appareil' => 'Test'])['json']['accessToken'];

        $me = $this->api('GET', '/api/mobile/v1/moi', bearer: $token);
        $this->assertNull($me['json']['user']['profile']['dateOfBirth']);
        $this->assertTrue($me['json']['user']['profile']['askBirthDate']);

        $res = $this->api('PATCH', '/api/mobile/v1/moi', ['dateOfBirth' => '1992-04-30'], $token);
        $this->assertSame(200, $res['status']);
        $this->assertSame('1992-04-30', $res['json']['user']['profile']['dateOfBirth']);
        $this->assertFalse($res['json']['user']['profile']['askBirthDate']);

        // Moins de 15 ans ou date impossible : refus en français, la date reste
        $young = (new \DateTimeImmutable('today'))->modify('-12 years')->format('Y-m-d');
        $res = $this->api('PATCH', '/api/mobile/v1/moi', ['dateOfBirth' => $young], $token);
        $this->assertSame(422, $res['status']);
        $this->assertStringContainsString('15 ans et plus', $res['json']['error'] ?? json_encode($res['json']));
        $this->assertSame(400, $this->api('PATCH', '/api/mobile/v1/moi', ['dateOfBirth' => 19920430], $token)['status']);
        $this->assertSame(422, $this->api('PATCH', '/api/mobile/v1/moi', ['dateOfBirth' => '1992-02-31'], $token)['status']);
        $this->assertSame('1992-04-30', $this->api('GET', '/api/mobile/v1/moi', bearer: $token)['json']['user']['profile']['dateOfBirth']);

        // Effacée, puis invitation écartée
        $res = $this->api('PATCH', '/api/mobile/v1/moi', ['dateOfBirth' => null], $token);
        $this->assertNull($res['json']['user']['profile']['dateOfBirth']);
        $this->assertTrue($res['json']['user']['profile']['askBirthDate']);
        $res = $this->api('PATCH', '/api/mobile/v1/moi', ['birthDatePromptDismissed' => true], $token);
        $this->assertFalse($res['json']['user']['profile']['askBirthDate']);

        // Interrupteur du message d'anniversaire
        $prefs = $this->api('GET', '/api/mobile/v1/notifications/preferences', bearer: $token);
        $this->assertTrue($prefs['json']['preferences']['notifyBirthday'] ?? $prefs['json']['notifyBirthday']);
        $this->api('PATCH', '/api/mobile/v1/notifications/preferences', ['notifyBirthday' => false], $token);
        $this->assertFalse($this->findUser($user->getEmail())->getNotificationPreference()->isNotifyBirthday());
    }
}
