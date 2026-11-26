<?php

namespace App\Tests\Notification;

use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Un seul message par jour » (parcours guidés, 26/11/2026) : le jour où un membre reçoit l'e-mail de sa séance, ses
 * autres rappels ne partent pas par e-mail (ils restent dans l'application) ; les messages importants passent toujours.
 */
final class SessionDayEmailTest extends WebTestCase
{
    use AccountTestTrait;

    public function testOtherRemindersKeepQuietByEmailOnASessionDay(): void
    {
        static::createClient();
        $notifier = static::getContainer()->get(Notifier::class);
        $user = $this->createUser();
        $other = $this->createUser();
        $notifier->holdRemindersForSessionDay([$user->getId()]);

        $this->assertTrue($notifier->notify($user, NotificationType::HABIT_REMINDER, 'Vos rituels', 'Pensez à vos rituels', null, 'test-rituels'), 'La notification reste dans l\'application.');
        $this->assertFalse($notifier->notify($user, NotificationType::TIP, 'Saviez-vous que…', 'Une astuce', null, 'test-astuce'), 'Le « Saviez-vous que… » (e-mail seulement) attend un autre jour.');
        $this->assertCount(0, $this->getMailerMessages());

        $this->assertTrue($notifier->notify($user, NotificationType::TRIAL_ENDING, 'Votre essai se termine', 'Dans 3 jours', null, 'test-essai'));
        $this->assertCount(1, $this->getMailerMessages(), 'Un message important passe toujours.');

        $this->assertTrue($notifier->notify($other, NotificationType::HABIT_REMINDER, 'Vos rituels', 'Pensez à vos rituels', null, 'test-rituels'));
        $this->assertCount(2, $this->getMailerMessages(), 'Sans séance ce jour-là, rien ne change.');
    }
}
