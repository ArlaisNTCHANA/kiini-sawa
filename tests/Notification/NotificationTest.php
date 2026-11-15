<?php

namespace App\Tests\Notification;

use App\Compte\Entity\User;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Notification\Service\ReminderService;
use App\Social\Entity\Connection;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Centre de notifications, préférences, rappels et désabonnement.
 */
class NotificationTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function notifier(): Notifier
    {
        return static::getContainer()->get(Notifier::class);
    }

    public function testBellPageAndMarkAsRead(): void
    {
        $user = $this->createUser();
        $this->notifier()->notify($user, NotificationType::CONNECTION_ACCEPTED, 'Partenariat accepté', 'Paul a accepté.', '/social');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/mes-plans/rituels');
        $this->assertSelectorExists('a[href="/notifications"]');
        $this->assertSelectorTextContains('a[href="/notifications"]', '1');

        $crawler = $this->client->request('GET', '/notifications');
        $this->assertSelectorTextContains('main', 'Partenariat accepté');
        $this->client->submit($crawler->filter('form[action^="/notifications/"][action$="/ouvrir"]')->form());
        $this->assertResponseRedirects('/social');
        $this->assertSame(0, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM notification WHERE user_id = ? AND `read` = 0', [$user->getId()]));
    }

    public function testCannotOpenSomeoneElsesNotification(): void
    {
        $owner = $this->createUser();
        $this->notifier()->notify($owner, NotificationType::CONNECTION_REQUEST, 'Demande', 'Message', '/social');
        $id = $this->em()->getRepository(Notification::class)->findOneBy(['user' => $owner])->getId();

        $intruder = $this->createUser();
        $this->notifier()->notify($intruder, NotificationType::CONNECTION_REQUEST, 'La sienne', 'Message', '/social');
        $this->client->loginUser($intruder);
        $crawler = $this->client->request('GET', '/notifications');
        $token = $crawler->filter('input[name=_csrf_token]')->attr('value');
        $this->client->request('POST', "/notifications/$id/ouvrir", ['_csrf_token' => $token]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testPreferencesAreRespectedAndRemindersAreNotDuplicated(): void
    {
        $user = $this->createUser();
        $prefs = $this->notifier()->preferences($user);
        $sunday = new \DateTimeImmutable('2026-11-15'); // un dimanche (jour par défaut)
        $reminders = static::getContainer()->get(ReminderService::class);

        $this->assertTrue($reminders->weeklyReview($user, $sunday));
        $this->assertCount(1, $this->getMailerMessages(), 'Rappel aussi envoyé par email');
        $this->assertFalse($reminders->weeklyReview($user, $sunday), 'Pas de doublon le même jour');
        $this->assertFalse($reminders->weeklyReview($user, $sunday->modify('+1 day')), 'Seulement le jour choisi');

        $prefs->setNotifyWeeklyReview(false);
        $this->em()->flush();
        $this->assertFalse($reminders->weeklyReview($user, $sunday->modify('+7 days')), 'Rappel désactivé dans le profil');
    }

    public function testInactivePartnerIsReported(): void
    {
        $user = $this->createUser();
        $partner = $this->createUser();
        $partner->setLastActiveAt(new \DateTimeImmutable('-10 days'));
        $connection = (new Connection())->setRequester($user)->setReceiver($partner)->setStatus('ACCEPTED')->setType('FRIEND');
        $this->em()->persist($connection);
        $this->em()->flush();
        $this->em()->refresh($user);

        $sent = static::getContainer()->get(ReminderService::class)->partners($user, new \DateTimeImmutable('today'));
        $this->assertSame(1, $sent);
        $this->assertStringContainsString('encouragement', $this->em()->getRepository(Notification::class)->findOneBy(['user' => $user])->getTitle());
    }

    public function testProfilePreferencesForm(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profil');
        $form = $crawler->selectButton('Enregistrer mes préférences')->form();
        $form['notifyHabitReminder']->untick();
        $form['weeklyReviewDay'] = '5';
        $this->client->submit($form);
        $this->assertResponseRedirects();

        $prefs = $this->findUser($user->getEmail())->getNotificationPreference();
        $this->assertFalse($prefs->isNotifyHabitReminder());
        $this->assertTrue($prefs->isNotifyWeeklyReview());
        $this->assertSame(5, $prefs->getWeeklyReviewDay());
    }

    public function testSignedUnsubscribeLinkWorksWithoutLogin(): void
    {
        $user = $this->createUser();
        $this->notifier()->preferences($user);
        $url = static::getContainer()->get(UriSigner::class)->sign(static::getContainer()->get('router')->generate('app_notification_unsubscribe', ['user' => $user->getId(), 'type' => 'HABIT_REMINDER'], UrlGeneratorInterface::ABSOLUTE_URL));

        $this->client->request('GET', $url.'X');
        $this->assertResponseStatusCodeSame(400, 'Lien falsifié refusé');

        $crawler = $this->client->request('GET', $url);
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Confirmer')->form());
        $this->assertSelectorTextContains('h1', 'C\'est noté');
        $this->assertFalse($this->findUser($user->getEmail())->getNotificationPreference()->isNotifyHabitReminder());
    }
}
