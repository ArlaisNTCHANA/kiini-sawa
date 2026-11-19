<?php

namespace App\Tests\Mobile;

use App\Compte\Entity\User;
use App\Mobile\Entity\MobilePushDevice;
use App\Mobile\Service\ExpoPushSender;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use App\Notification\Repository\NotificationRepository;
use App\Notification\Service\Notifier;
use App\Notification\Service\ReminderService;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MobileNotificationTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        ExpoPushSender::$testTransport = null;
    }

    protected function tearDown(): void
    {
        ExpoPushSender::$testTransport = null;
        parent::tearDown();
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{status: int, json: array<string, mixed>, headers: \Symfony\Component\HttpFoundation\ResponseHeaderBag}
     */
    private function api(string $method, string $path, ?array $payload = null, ?string $bearer = null): array
    {
        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $payload) {
            $headers['CONTENT_TYPE'] = 'application/json';
        }
        if (null !== $bearer) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$bearer;
        }

        $this->client->request(
            $method,
            $path,
            server: $headers,
            content: null !== $payload ? json_encode($payload, \JSON_THROW_ON_ERROR) : null
        );

        $response = $this->client->getResponse();
        $decoded = json_decode((string) $response->getContent(), true);

        return [
            'status' => $response->getStatusCode(),
            'json' => \is_array($decoded) ? $decoded : [],
            'headers' => $response->headers,
        ];
    }

    private function login(string $email, string $password = 'mot-de-passe-solide'): string
    {
        $res = $this->api('POST', '/api/mobile/v1/connexion', [
            'email' => $email,
            'password' => $password,
            'appareil' => 'iPhone Test',
        ]);
        $this->assertSame(200, $res['status']);

        return $res['json']['accessToken'];
    }

    public function testRegisterAndUnregisterDevice(): void
    {
        $user = $this->createUser();
        $token = $this->login((string) $user->getEmail());
        $expoToken = 'ExponentPushToken[Abc123_xyz-99]';

        // Format invalide
        $res = $this->api('POST', '/api/mobile/v1/notifications/appareil', [
            'expoToken' => 'token-invalide',
        ], $token);
        $this->assertSame(400, $res['status']);
        $this->assertSame('invalid_push_device', $res['json']['code']);

        // Enregistrement valide
        $res = $this->api('POST', '/api/mobile/v1/notifications/appareil', [
            'expoToken' => $expoToken,
            'platform' => 'ios',
            'appareil' => 'iPhone 15',
        ], $token);
        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['hasDevice']);
        $this->assertSame('ios', $res['json']['platform']);

        $device = $this->em()->getRepository(MobilePushDevice::class)->findOneByToken($expoToken);
        $this->assertNotNull($device);
        $this->assertSame('iPhone 15', $device->getDeviceName());

        // Ré-enregistrement du même jeton (mise à jour lastSeenAt)
        $res = $this->api('POST', '/api/mobile/v1/notifications/appareil', [
            'expoToken' => $expoToken,
            'platform' => 'ios',
            'appareil' => 'iPhone 15 Pro',
        ], $token);
        $this->assertSame(200, $res['status']);
        $this->assertSame(1, $this->em()->getRepository(MobilePushDevice::class)->countForUser($user));

        // Désinscription
        $res = $this->api('DELETE', '/api/mobile/v1/notifications/appareil', [
            'expoToken' => $expoToken,
        ], $token);
        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['json']['hasDevice']);
        $this->assertNull($this->em()->getRepository(MobilePushDevice::class)->findOneByToken($expoToken));
    }

    public function testMaxDevicesPerUserIsEnforced(): void
    {
        $user = $this->createUser();
        $token = $this->login((string) $user->getEmail());

        for ($i = 1; $i <= ExpoPushSender::MAX_DEVICES; ++$i) {
            $res = $this->api('POST', '/api/mobile/v1/notifications/appareil', [
                'expoToken' => "ExponentPushToken[Device_{$i}]",
            ], $token);
            $this->assertSame(200, $res['status']);
        }

        // Le 11e appareil doit échouer
        $res = $this->api('POST', '/api/mobile/v1/notifications/appareil', [
            'expoToken' => 'ExponentPushToken[Device_OverLimit]',
        ], $token);
        $this->assertSame(400, $res['status']);
        $this->assertStringContainsString('maximal', $res['json']['error']);
    }

    public function testNotificationPreferencesGetAndPatch(): void
    {
        $user = $this->createUser();
        $token = $this->login((string) $user->getEmail());

        $res = $this->api('GET', '/api/mobile/v1/notifications/preferences', null, $token);
        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['preferences']['enableInApp']);
        $this->assertSame('09:00', $res['json']['preferences']['preferredTime']);
        $this->assertSame(0, $res['json']['preferences']['weeklyReviewDay']);
        $this->assertFalse($res['json']['hasPushDevice']);

        // Heure invalide
        $res = $this->api('PATCH', '/api/mobile/v1/notifications/preferences', [
            'preferredTime' => '25:99',
        ], $token);
        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_time_format', $res['json']['code']);

        // Jour invalide
        $res = $this->api('PATCH', '/api/mobile/v1/notifications/preferences', [
            'weeklyReviewDay' => 7,
        ], $token);
        $this->assertSame(422, $res['status']);
        $this->assertSame('invalid_review_day', $res['json']['code']);

        // Mise à jour valide
        $res = $this->api('PATCH', '/api/mobile/v1/notifications/preferences', [
            'notifyHabitReminder' => false,
            'preferredTime' => '07:30',
            'weeklyReviewDay' => 6,
        ], $token);
        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['json']['preferences']['notifyHabitReminder']);
        $this->assertSame('07:30', $res['json']['preferences']['preferredTime']);
        $this->assertSame(6, $res['json']['preferences']['weeklyReviewDay']);
    }

    public function testSendTestNotificationWithRateLimit(): void
    {
        $user = $this->createUser();
        $token = $this->login((string) $user->getEmail());

        // Sans appareil : 400
        $res = $this->api('POST', '/api/mobile/v1/notifications/essai', null, $token);
        $this->assertSame(400, $res['status']);
        $this->assertSame('no_push_device', $res['json']['code']);

        // Enregistrer un appareil
        $this->api('POST', '/api/mobile/v1/notifications/appareil', [
            'expoToken' => 'ExponentPushToken[Test_Phone]',
        ], $token);

        $sentCount = 0;
        ExpoPushSender::$testTransport = function (array $messages) use (&$sentCount): array {
            $sentCount += \count($messages);
            return array_map(fn () => ['status' => 'ok'], $messages);
        };

        // 3 essais autorisés
        for ($i = 0; $i < 3; ++$i) {
            $res = $this->api('POST', '/api/mobile/v1/notifications/essai', null, $token);
            $this->assertSame(200, $res['status']);
            $this->assertSame(1, $res['json']['sent']);
        }
        $this->assertSame(3, $sentCount);

        // 4e essai dans l'heure : 429
        $res = $this->api('POST', '/api/mobile/v1/notifications/essai', null, $token);
        $this->assertSame(429, $res['status']);
        $this->assertSame('rate_limited', $res['json']['code']);
    }

    public function testPushSenderDecoratorIntegratesWithReminderServiceAndAutoPrunesUnregistered(): void
    {
        $user = $this->createUser();
        $habit = (new Habit())->setTitle('Prier')->setFrequency(HabitFrequency::DAILY);
        $habit->setUser($user);
        $this->em()->persist($habit);
        $this->em()->flush();

        $sender = static::getContainer()->get(ExpoPushSender::class);
        $sender->register($user, 'ExponentPushToken[ExpiredDevice]');

        $reminders = static::getContainer()->get(ReminderService::class);
        $today = new \DateTimeImmutable('today');

        // Test auto-pruning when Expo reports DeviceNotRegistered
        $transportCalled = false;
        ExpoPushSender::$testTransport = function (array $messages) use (&$transportCalled): array {
            $transportCalled = true;
            return [
                ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']],
            ];
        };

        $this->assertTrue($reminders->dailyRituals($user, $today));
        $this->assertTrue($transportCalled);

        // L'appareil doit avoir été supprimé de la base de données
        $this->assertFalse($sender->hasDevice($user));
    }

    public function testInAppNotificationsListAndMarkRead(): void
    {
        $user1 = $this->createUser();
        $token1 = $this->login((string) $user1->getEmail());

        $user2 = $this->createUser();
        $token2 = $this->login((string) $user2->getEmail());

        $user1Entity = $this->em()->find(User::class, $user1->getId());
        $this->assertNotNull($user1Entity);

        $notifier = static::getContainer()->get(Notifier::class);
        $notifier->notify($user1Entity, NotificationType::WEEKLY_REVIEW_REMINDER, 'Revue de la semaine', 'Prenez 15 minutes.');
        $notifier->notify($user1Entity, NotificationType::HABIT_REMINDER, 'Rappels du jour', 'Vos rituels vous attendent.');

        $res1 = $this->api('GET', '/api/mobile/v1/notifications', null, $token1);
        $this->assertSame(200, $res1['status']);
        $this->assertSame(2, $res1['json']['unreadCount']);
        $this->assertCount(2, $res1['json']['notifications']);
        $firstId = $res1['json']['notifications'][0]['id'];

        // Isolation : user2 ne voit aucune notification
        $res2 = $this->api('GET', '/api/mobile/v1/notifications', null, $token2);
        $this->assertSame(0, $res2['json']['unreadCount']);
        $this->assertEmpty($res2['json']['notifications']);

        // user2 ne peut pas marquer comme lue la notification de user1
        $resForbidden = $this->api('POST', "/api/mobile/v1/notifications/{$firstId}/lu", null, $token2);
        $this->assertSame(404, $resForbidden['status']);

        // user1 marque comme lue
        $resMark = $this->api('POST', "/api/mobile/v1/notifications/{$firstId}/lu", null, $token1);
        $this->assertSame(200, $resMark['status']);
        $this->assertSame(1, $resMark['json']['unreadCount']);

        // user1 marque tout comme lu
        $resAll = $this->api('POST', '/api/mobile/v1/notifications/tout-lire', null, $token1);
        $this->assertSame(200, $resAll['status']);
        $this->assertSame(0, $resAll['json']['unreadCount']);
    }
}
