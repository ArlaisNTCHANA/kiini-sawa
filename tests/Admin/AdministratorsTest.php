<?php

namespace App\Tests\Admin;

use App\Admin\Entity\AdminAction;
use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Admin › Réglages › Administrateurs et journal (28/11/2026) : donner ou retirer l'accès avec garde-fous, et chaque
 * action réussie de l'administration notée d'elle-même dans le journal (rien quand elle échoue).
 */
class AdministratorsTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;
    private User $admin;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->admin = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $this->client->loginUser($this->admin);
    }

    private function post(string $url, array $data = []): void
    {
        $this->client->request('POST', $url, $data + ['_csrf_token' => FixedCsrfTokenManager::VALUE]);
    }

    private function isAdmin(User $u): bool
    {
        $this->em()->clear();

        return \in_array('ROLE_ADMIN', $this->em()->find(User::class, $u->getId())->getRoles(), true);
    }

    private function lastAction(): ?AdminAction
    {
        $this->em()->clear();

        return $this->em()->getRepository(AdminAction::class)->findOneBy([], ['id' => 'DESC']);
    }

    public function testGiveAndRemoveAccessWithGuards(): void
    {
        $member = $this->createUser();
        $unverified = $this->createUser(verified: false);

        $this->post('/admin/administrateurs/ajouter', ['email' => $member->getEmail()]);
        $this->assertFalse($this->isAdmin($member), 'Sans la case de confirmation, rien');
        $this->post('/admin/administrateurs/ajouter', ['email' => $unverified->getEmail(), 'confirm' => '1']);
        $this->assertFalse($this->isAdmin($unverified), 'Adresse non vérifiée : refusé');

        $before = $this->lastAction()?->getId() ?? 0;
        $this->post('/admin/administrateurs/ajouter', ['email' => strtoupper((string) $member->getEmail()), 'confirm' => '1']);
        $this->assertTrue($this->isAdmin($member));
        $action = $this->lastAction();
        $this->assertGreaterThan($before, $action->getId());
        $this->assertSame('admin@kiinisawa.com', $action->getActorEmail());
        $this->assertStringContainsString('est maintenant administrateur', $action->getMessage());

        $this->post('/admin/administrateurs/'.$this->admin->getId().'/retirer');
        $this->assertTrue($this->isAdmin($this->admin), 'On ne se retire pas soi-même');

        $this->post('/admin/administrateurs/'.$member->getId().'/retirer');
        $this->assertFalse($this->isAdmin($member));
        $this->assertSame((string) $member->getEmail(), $this->lastAction()->getTarget());

        $this->client->request('GET', '/admin/administrateurs');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Journal des actions');
        $this->assertSelectorTextContains('main', 'a été retiré');
    }

    public function testEverySuccessfulAdminActionIsJournaledButNotAFailedOne(): void
    {
        $member = $this->createUser();
        $before = $this->lastAction()?->getId() ?? 0;
        $this->post('/admin/utilisateurs/'.$member->getId().'/offrir', ['months' => 3]);
        $action = $this->lastAction();
        $this->assertGreaterThan($before, $action->getId());
        $this->assertStringContainsString('3 mois de PRO offerts', $action->getMessage());
        $this->assertSame((string) $member->getEmail(), $action->getTarget());

        // Une suppression refusée (mauvaise confirmation) n'est pas notée
        $last = $action->getId();
        $this->post('/admin/utilisateurs/'.$member->getId().'/supprimer', ['confirm' => 'pas la bonne']);
        $this->assertSame($last, $this->lastAction()->getId());
    }

    public function testMembersCannotSeeTheAdministrators(): void
    {
        $this->client->loginUser($this->createUser());
        $this->client->request('GET', '/admin/administrateurs');
        $this->assertResponseStatusCodeSame(403);
    }
}
