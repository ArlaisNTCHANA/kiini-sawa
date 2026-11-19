<?php

namespace App\Tests\Notification;

use App\Compte\Entity\User;
use App\Notification\Entity\PushSubscription;
use App\Notification\Service\PushEndpointValidator;
use App\Notification\Service\PushSender;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Audit de sécurité, lot 2 : SSRF du Web Push (liste blanche, DNS, plafond d'appareils, essai limité). */
class PushSecurityTest extends WebTestCase
{
    use AccountTestTrait;

    protected function setUp(): void
    {
        PushEndpointValidator::$resolver = fn (string $host): array => ['142.250.74.10'];
    }

    protected function tearDown(): void
    {
        PushEndpointValidator::$resolver = null;
        parent::tearDown();
    }

    /** @return iterable<string, array{string}> */
    public static function rejectedEndpoints(): iterable
    {
        yield 'http' => ['http://fcm.googleapis.com/fcm/send/x'];
        yield 'localhost' => ['https://localhost/x'];
        yield 'IP littérale' => ['https://169.254.169.254/latest/meta-data'];
        yield 'IPv6 locale' => ['https://[::1]/x'];
        yield 'hôte quelconque' => ['https://evil.example.com/push'];
        yield 'sous-domaine piégé' => ['https://fcm.googleapis.com.evil.example/x'];
        yield 'préfixe piégé' => ['https://evilfcm.googleapis.com.attacker.io/x'];
        yield 'suffixe sans sous-domaine' => ['https://push.services.mozilla.com/x'];
        yield 'identifiants dans l\'URL' => ['https://fcm.googleapis.com@evil.example/x'];
        yield 'utilisateur et hôte officiel' => ['https://user:pw@fcm.googleapis.com/x'];
        yield 'port inhabituel' => ['https://fcm.googleapis.com:8443/x'];
        yield 'espace' => ['https://fcm.googleapis.com/a b'];
        yield 'protocole inconnu' => ['gopher://fcm.googleapis.com/x'];
        yield 'trop long' => ['https://fcm.googleapis.com/'.str_repeat('a', 2100)];
        yield 'vide' => [''];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedEndpoints')]
    public function testRejectedEndpoints(string $endpoint): void
    {
        $this->assertFalse((new PushEndpointValidator())->isAllowed($endpoint), $endpoint);
    }

    /** @return iterable<string, array{string}> */
    public static function acceptedEndpoints(): iterable
    {
        yield 'Google' => ['https://fcm.googleapis.com/fcm/send/abc'];
        yield 'Mozilla' => ['https://updates.push.services.mozilla.com/wpush/v2/abc'];
        yield 'Mozilla (sous-domaine)' => ['https://eu.push.services.mozilla.com/wpush/v2/abc'];
        yield 'Apple' => ['https://web.push.apple.com/abc'];
        yield 'Microsoft' => ['https://db5p.notify.windows.com/?token=abc'];
        yield 'port 443 explicite' => ['https://fcm.googleapis.com:443/fcm/send/abc'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('acceptedEndpoints')]
    public function testOfficialEndpointsAreAccepted(string $endpoint): void
    {
        $this->assertTrue((new PushEndpointValidator())->isAllowed($endpoint), $endpoint);
    }

    public function testAHostResolvingToAPrivateOrUnknownAddressIsRefused(): void
    {
        $validator = new PushEndpointValidator();
        foreach (['10.0.0.5', '192.168.1.10', '172.16.0.1', '127.0.0.1', '169.254.169.254', '0.0.0.0', '::1', 'fd00::1', 'fe80::1'] as $ip) {
            PushEndpointValidator::$resolver = fn (string $h): array => ['142.250.74.10', $ip];
            $this->assertFalse($validator->isAllowed('https://fcm.googleapis.com/fcm/send/x'), 'une des adresses est privée : '.$ip);
        }
        PushEndpointValidator::$resolver = fn (string $h): array => [];
        $this->assertFalse($validator->isAllowed('https://fcm.googleapis.com/fcm/send/x'), 'résolution impossible : refus (fail closed)');
        PushEndpointValidator::$resolver = fn (string $h): array => ['2a00:1450:4007:80b::200e'];
        $this->assertTrue($validator->isAllowed('https://fcm.googleapis.com/fcm/send/x'), 'IPv6 publique acceptée');
    }

    private function post(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, array $body): array
    {
        $client->request('POST', '/notifications/appareil', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], content: json_encode($body));

        return ['status' => $client->getResponse()->getStatusCode(), 'json' => json_decode($client->getResponse()->getContent(), true)];
    }

    public function testTheSubscribeRouteRefusesForeignAndInternalAddresses(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        foreach (['http://169.254.169.254/latest/meta-data', 'https://evil.example.com/push', 'https://localhost/x', 'https://fcm.googleapis.com.evil.example/x'] as $endpoint) {
            $r = $this->post($client, ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'k', 'auth' => 'a']]);
            $this->assertSame(400, $r['status'], $endpoint);
        }
        $this->assertSame(0, $this->em()->getRepository(PushSubscription::class)->count(['user' => $user->getId()]));
    }

    public function testNoMoreThanTenDevicesPerAccountButTheSameDeviceCanRenew(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $tag = uniqid();
        for ($i = 1; $i <= PushSender::MAX_DEVICES; ++$i) {
            $this->assertSame(200, $this->post($client, ['endpoint' => "https://fcm.googleapis.com/fcm/send/$tag-$i", 'keys' => ['p256dh' => 'k', 'auth' => 'a']])['status']);
        }
        $over = $this->post($client, ['endpoint' => "https://fcm.googleapis.com/fcm/send/$tag-extra", 'keys' => ['p256dh' => 'k', 'auth' => 'a']]);
        $this->assertSame(400, $over['status']);
        $this->assertStringContainsString('maximal', $over['json']['error']);
        $this->assertSame(200, $this->post($client, ['endpoint' => "https://fcm.googleapis.com/fcm/send/$tag-1", 'keys' => ['p256dh' => 'k2', 'auth' => 'a2']])['status'], 'le même appareil se réinscrit');
        $this->assertSame(PushSender::MAX_DEVICES, $this->em()->getRepository(PushSubscription::class)->count(['user' => $user->getId()]));
        // Retirer un appareil est toujours possible, même si son adresse ne serait plus acceptée
        PushEndpointValidator::$resolver = fn (string $h): array => ['10.0.0.1'];
        $this->assertSame(200, $this->post($client, ['action' => 'remove', 'endpoint' => "https://fcm.googleapis.com/fcm/send/$tag-1"])['status']);
        $this->assertSame(PushSender::MAX_DEVICES - 1, $this->em()->getRepository(PushSubscription::class)->count(['user' => $user->getId()]));
    }

    public function testAnAlteredSubscriptionIsDroppedAndNeverCalledAtSendTime(): void
    {
        static::createClient();
        $user = $this->createUser();
        $em = $this->em();
        // Abonnement ancien ou altéré en base : adresse interne
        $em->persist(new PushSubscription($user, 'https://169.254.169.254/latest/meta-data/'.uniqid(), 'k', 'a', null));
        $em->flush();
        $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
        $sender = new PushSender($this->em(), new PushEndpointValidator(), new \Psr\Log\NullLogger(), $keys['publicKey'], $keys['privateKey'], 'mailto:test@example.com');
        $this->assertSame(0, $sender->send($this->em()->find(User::class, $user->getId()), 'Titre', 'Corps'));
        $this->assertSame(0, $this->em()->getRepository(PushSubscription::class)->count(['user' => $user->getId()]), 'l\'abonnement suspect est supprimé');
    }

    public function testTheTestNotificationIsLimitedToThreePerHour(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $limits = static::getContainer()->get(\App\Shared\Security\RateLimits::class);
        $this->assertTrue($limits->allow('push_test', (string) $user->getId()));
        $this->assertTrue($limits->allow('push_test', (string) $user->getId()));
        $this->assertTrue($limits->allow('push_test', (string) $user->getId()));
        $this->assertFalse($limits->allow('push_test', (string) $user->getId()), 'la 4e demande de la même heure est refusée');
        $this->assertTrue($limits->allow('push_test', (string) ($user->getId() + 100000)), 'un autre compte n\'est pas touché');
    }
}
