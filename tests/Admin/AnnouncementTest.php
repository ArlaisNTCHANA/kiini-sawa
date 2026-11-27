<?php

namespace App\Tests\Admin;

use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Notification\Entity\Announcement;
use App\Notification\Entity\Notification;
use App\Notification\Enum\NotificationType;
use App\Notification\Service\Notifier;
use App\Organisation\Entity\Membership;
use App\Organisation\Entity\Organisation;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Admin › Écrire aux membres (27/11/2026) : seul le public choisi est prévenu (jamais un administrateur ni une adresse
 * non vérifiée), une seule fois même si l'envoi reprend, l'e-mail respecte « Les annonces de l'équipe », et l'essai ne
 * part que vers l'administrateur.
 */
class AnnouncementTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
    }

    private function post(string $url, array $data = []): void
    {
        $this->client->request('POST', $url, $data + ['_csrf_token' => FixedCsrfTokenManager::VALUE]);
    }

    /** @return array{0: Organisation, 1: User, 2: User, 3: User} organisation, membre, membre sans e-mail d'annonces, adresse non vérifiée */
    private function organisation(): array
    {
        $org = new Organisation('Église annonce '.uniqid(), 'EGLISE', null);
        $member = $this->createUser();
        $quiet = $this->createUser();
        $unverified = $this->createUser(verified: false);
        $this->em()->persist($org);
        foreach ([$member, $quiet, $unverified] as $u) {
            $this->em()->persist(new Membership($org, $u));
        }
        static::getContainer()->get(Notifier::class)->preferences($quiet)->setNotifyAnnouncements(false);
        $this->em()->flush();

        return [$org, $member, $quiet, $unverified];
    }

    private function create(Organisation $org, bool $withEmail = true): Announcement
    {
        $this->post('/admin/messages/nouvelle', ['title' => 'Nouveau parcours', 'message' => "Bonjour,\n\nUn parcours vous attend.", 'actionUrl' => '/academie',
            'actionLabel' => 'Voir', 'audience' => 'ORGANISATION', 'organisation' => $org->getId()] + ($withEmail ? ['withEmail' => '1'] : []));
        $this->assertResponseRedirects();

        return $this->em()->getRepository(Announcement::class)->findOneBy([], ['id' => 'DESC']);
    }

    private function notified(User $u): int
    {
        return $this->em()->getRepository(Notification::class)->count(['user' => $u, 'type' => NotificationType::ANNOUNCEMENT]);
    }

    public function testOnlyTheChosenMembersOnceAndEmailRespectsTheirChoice(): void
    {
        [$org, $member, $quiet, $unverified] = $this->organisation();
        $a = $this->create($org);

        $this->client->request('GET', '/admin/messages/'.$a->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Envoyer à 1 membre', 'Ni l\'adresse non vérifiée, ni celui qui a coupé les annonces');

        // L'essai : un e-mail à l'administrateur, aucune notification
        $this->post('/admin/messages/'.$a->getId().'/essai');
        $this->assertCount(1, $this->getMailerMessages());
        $this->assertEmailAddressContains($this->getMailerMessages()[0], 'To', 'admin@kiinisawa.com');
        $this->assertSame(0, $this->notified($member));

        $this->post('/admin/messages/'.$a->getId().'/envoyer');
        $this->assertCount(1, $this->getMailerMessages(), 'Un seul e-mail : celui qui a coupé les annonces ne reçoit rien');
        $this->assertEmailAddressContains($this->getMailerMessages()[0], 'To', (string) $member->getEmail());
        $this->em()->clear();
        $a = $this->em()->find(Announcement::class, $a->getId());
        $this->assertSame(Announcement::STATUS_SENT, $a->getStatus());
        $this->assertSame(1, $a->getReached());

        $member = $this->em()->find(User::class, $member->getId());
        $quiet = $this->em()->find(User::class, $quiet->getId());
        $unverified = $this->em()->find(User::class, $unverified->getId());
        $this->assertSame(1, $this->notified($member));
        $this->assertSame(0, $this->notified($quiet), 'Annonces coupées : rien, pas même dans l\'application');
        $this->assertSame(0, $this->notified($unverified));

        // Reprendre un envoi terminé ne renvoie rien
        $a->setStatus(Announcement::STATUS_SENDING)->setCursor(0);
        $this->em()->flush();
        (new CommandTester((new Application(static::$kernel))->find('app:annonces:envoyer')))->execute([]);
        $this->em()->clear();
        $this->assertSame(1, $this->notified($this->em()->find(User::class, $member->getId())), 'Jamais deux fois');

        // Une annonce partie ne se modifie plus
        $this->post('/admin/messages/'.$a->getId().'/modifier', ['title' => 'Autre', 'message' => 'Autre', 'audience' => 'ALL']);
        $this->em()->clear();
        $this->assertSame('Nouveau parcours', $this->em()->find(Announcement::class, $a->getId())->getTitle());
    }

    public function testApplicationOnlyMeansNoEmail(): void
    {
        [$org, $member] = $this->organisation();
        $a = $this->create($org, withEmail: false);
        $this->post('/admin/messages/'.$a->getId().'/envoyer');
        $this->assertCount(0, $this->getMailerMessages());
        $this->em()->clear();
        $this->assertSame(1, $this->notified($this->em()->find(User::class, $member->getId())));
    }

    public function testMembersCannotWriteToEveryone(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/admin/messages');
        $this->assertResponseStatusCodeSame(403);
    }
}
