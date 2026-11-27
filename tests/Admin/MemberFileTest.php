<?php

namespace App\Tests\Admin;

use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Journal\Entity\ReflectionNote;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Fiche d'un membre dans l'administration (27/11/2026) : de quoi l'aider, sans lire sa vie (compteurs, jamais le texte
 * de son journal), et trois gestes d'assistance : renvoyer le code, corriger l'adresse, supprimer le compte à sa demande.
 */
class MemberFileTest extends WebTestCase
{
    use AccountTestTrait;

    private function admin(): KernelBrowser
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));

        return $client;
    }

    private function post(KernelBrowser $client, User $member, string $action, array $data = []): void
    {
        $client->request('POST', '/admin/utilisateurs/'.$member->getId().'/'.$action, $data + ['_csrf_token' => FixedCsrfTokenManager::VALUE]);
    }

    public function testFileShowsCountersButNeverTheJournal(): void
    {
        $client = $this->admin();
        $member = $this->createUser();
        $this->em()->persist((new ReflectionNote())->setUser($member)->setContent('Mon secret le plus intime')->setDate(new \DateTime())->setType(\App\Journal\Enum\ReflectionType::REFLECTION));
        $this->em()->flush();

        $crawler = $client->request('GET', '/admin/utilisateurs');
        $this->assertCount(1, $crawler->filter('a[href="/admin/utilisateurs/'.$member->getId().'"]'), 'La liste mène à la fiche');

        $client->request('GET', '/admin/utilisateurs/'.$member->getId());
        $this->assertResponseIsSuccessful();
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString((string) $member->getEmail(), $html);
        $this->assertStringContainsString('note dans le Journal', $html);
        $this->assertStringNotContainsString('Mon secret le plus intime', $html);
        $this->assertSelectorTextContains('.ks-admin-side .is-active, .ks-admin-side [aria-current]', 'Membres');
    }

    public function testResendCodeAndCorrectTheAddress(): void
    {
        $client = $this->admin();
        $member = $this->createUser(verified: false);
        $this->post($client, $member, 'code');
        $this->assertCount(1, $this->getMailerMessages());
        $this->assertResponseRedirects('/admin/utilisateurs/'.$member->getId());

        $taken = $this->createUser();
        $this->post($client, $member, 'email', ['email' => $taken->getEmail()]);
        $this->assertSame((string) $member->getEmail(), (string) $this->findUser((string) $member->getEmail())?->getEmail(), 'Adresse déjà prise : rien ne change');

        $new = 'corrigee-'.uniqid().'@exemple.org';
        $this->post($client, $member, 'email', ['email' => strtoupper($new)]);
        $this->em()->clear();
        $moved = $this->findUser($new);
        $this->assertNotNull($moved);
        $this->assertFalse($moved->isVerified(), 'La nouvelle adresse est à vérifier');
        $this->assertCount(1, $this->getMailerMessages());
        $this->assertEmailAddressContains($this->getMailerMessages()[0], 'To', $new);
    }

    public function testDeleteOnlyWithTheAddressRetypedAndNeverAnAdmin(): void
    {
        $client = $this->admin();
        $member = $this->createUser();
        $id = $member->getId();
        $email = (string) $member->getEmail();

        $this->post($client, $member, 'supprimer', ['confirm' => 'pas la bonne adresse']);
        $this->em()->clear();
        $this->assertNotNull($this->em()->find(User::class, $id), 'Sans la bonne adresse, rien n\'est supprimé');

        $this->post($client, $this->em()->find(User::class, $id), 'supprimer', ['confirm' => strtoupper($email)]);
        $this->assertResponseRedirects('/admin/utilisateurs');
        $this->assertCount(1, $this->getMailerMessages(), 'Le membre reçoit une confirmation');
        $this->em()->clear();
        $this->assertNull($this->em()->find(User::class, $id));

        $admin = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $this->post($client, $admin, 'supprimer', ['confirm' => 'admin@kiinisawa.com']);
        $this->em()->clear();
        $this->assertNotNull($this->findUser('admin@kiinisawa.com'), 'Un administrateur ne se supprime jamais depuis la fiche');
    }

    public function testMembersCannotOpenAFile(): void
    {
        $client = static::createClient();
        $member = $this->createUser();
        $client->loginUser($member);
        $client->request('GET', '/admin/utilisateurs/'.$member->getId());
        $this->assertResponseStatusCodeSame(403);
    }
}
