<?php

namespace App\Tests\Notification;

use App\Notification\Entity\PushSubscription;
use App\Notification\Service\PushSender;
use App\Notification\Service\ReminderService;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Notifications sur le téléphone : enregistrement de l'appareil, désactivation, rappel des rituels du matin
 * (uniquement pour les appareils autorisés, jamais par email).
 */
class PushTest extends WebTestCase
{
    use AccountTestTrait;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/test-';

    public function testDeviceRegistrationAndRemoval(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);
        $endpoint = self::ENDPOINT.uniqid();
        $body = json_encode(['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BKey', 'auth' => 'secret']]);

        $client->request('POST', '/notifications/appareil', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], $body);
        $this->assertResponseIsSuccessful();
        $this->assertTrue(static::getContainer()->get(PushSender::class)->hasDevice($user));

        // Même appareil deux fois : un seul enregistrement
        $client->request('POST', '/notifications/appareil', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], $body);
        $this->assertSame(1, $this->em()->getRepository(PushSubscription::class)->count(['user' => $user]));

        $client->request('POST', '/notifications/appareil', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], json_encode(['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BKey', 'auth' => 'secret'], 'action' => 'remove']));
        $this->assertFalse(static::getContainer()->get(PushSender::class)->hasDevice($user));

        $client->request('POST', '/notifications/appareil', [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost'], json_encode(['endpoint' => 'http://pas-https', 'keys' => ['p256dh' => 'a', 'auth' => 'b']]));
        $this->assertResponseStatusCodeSame(400);
    }

    public function testMorningRitualsOnlyForAuthorisedDevicesAndNeverByEmail(): void
    {
        static::createClient();
        $user = $this->createUser();
        $habit = (new Habit())->setTitle('Prier')->setFrequency(HabitFrequency::DAILY);
        $habit->setUser($user);
        $this->em()->persist($habit);
        $this->em()->flush();
        $reminders = static::getContainer()->get(ReminderService::class);
        $today = new \DateTimeImmutable('today');

        $this->assertFalse($reminders->dailyRituals($user, $today), 'Sans appareil autorisé : rien');

        static::getContainer()->get(PushSender::class)->subscribe($user, self::ENDPOINT.uniqid(), 'BKey', 'secret', 'test');
        $before = \count($this->getMailerMessages());
        $this->assertTrue($reminders->dailyRituals($user, $today));
        $this->assertCount($before, $this->getMailerMessages(), 'Aucun email pour le rappel du matin');
        $this->assertFalse($reminders->dailyRituals($user, $today), 'Une fois par jour');
    }
}
