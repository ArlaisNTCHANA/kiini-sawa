<?php

namespace App\Tests\Compte;

use App\Compte\Entity\User;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Mon Profil : informations, mot de passe, email, export et suppression (RGPD).
 */
class ProfileTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function loginAs(User $user): void
    {
        $this->client->loginUser($user);
    }

    private function submitProfileForm(string $button, array $values): void
    {
        $crawler = $this->client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton($button)->form(), $values);
    }

    public function testMenuLinksToProfile(): void
    {
        $this->loginAs($this->createUser());
        $crawler = $this->client->request('GET', '/mes-plans/rituels');
        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('a[href="/profil"]')->count());
    }

    public function testUpdateInformation(): void
    {
        $user = $this->createUser();
        $this->loginAs($user);
        $this->submitProfileForm('Enregistrer', ['firstName' => 'Lydie', 'city' => 'Douala', 'profession' => '']);

        $this->assertResponseRedirects('/profil');
        $profile = $this->findUser($user->getEmail())->getUserProfile();
        $this->assertSame('Lydie', $profile->getFirstName());
        $this->assertSame('Douala', $profile->getCity());
        $this->assertNull($profile->getProfession());
    }

    /** Ouvre une session de l'application mobile pour ce compte et renvoie son jeton d'accès. */
    private function openMobileSession(\App\Compte\Entity\User $user): string
    {
        return static::getContainer()->get(\App\Mobile\Service\MobileTokenService::class)->issue($user, 'Téléphone de test')['accessToken'];
    }

    private function assertMobileSessionClosed(string $accessToken): void
    {
        $this->assertNull(
            static::getContainer()->get(\App\Mobile\Service\MobileTokenService::class)->findActiveByAccessToken($accessToken),
            'La session de l\'application mobile doit être fermée'
        );
    }

    public function testChangePasswordRequiresCurrentPassword(): void
    {
        $user = $this->createUser('mot-de-passe-actuel');
        $mobileToken = $this->openMobileSession($user);
        $this->loginAs($user);
        $hasher = static::getContainer()->get('security.user_password_hasher');

        $this->submitProfileForm('Modifier le mot de passe', ['currentPassword' => 'faux', 'newPassword' => 'un tout nouveau mot de passe']);
        $this->assertTrue($hasher->isPasswordValid($this->findUser($user->getEmail()), 'mot-de-passe-actuel'));
        $this->assertNotNull(static::getContainer()->get(\App\Mobile\Service\MobileTokenService::class)->findActiveByAccessToken($mobileToken), 'Un essai refusé ne ferme rien');

        $this->submitProfileForm('Modifier le mot de passe', ['currentPassword' => 'mot-de-passe-actuel', 'newPassword' => 'un tout nouveau mot de passe']);
        $this->assertTrue($hasher->isPasswordValid($this->findUser($user->getEmail()), 'un tout nouveau mot de passe'));
        $this->assertMobileSessionClosed($mobileToken);

        // L'utilisateur reste connecté après le changement
        $this->client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();
    }

    public function testChangeEmailWithCodeSentToNewAddress(): void
    {
        $user = $this->createUser('mot-de-passe-actuel');
        $mobileToken = $this->openMobileSession($user);
        $this->loginAs($user);
        $newEmail = $this->uniqueEmail('nouvelle');

        $this->submitProfileForm('Recevoir un code', ['newEmail' => $newEmail, 'currentPassword' => 'mot-de-passe-actuel']);
        $mail = $this->lastEmail();
        $this->assertSame($newEmail, $mail->getTo()[0]->getAddress());
        $this->assertSame($user->getEmail(), $this->findUser($user->getEmail())?->getEmail(), 'Rien ne change avant la confirmation');

        $this->submitProfileForm('Confirmer', ['code' => $this->codeFrom($mail)]);
        $this->assertNotNull($this->findUser($newEmail));
        $this->assertNull($this->findUser($user->getEmail()));
        $this->assertMobileSessionClosed($mobileToken);

        // Toujours connecté avec la nouvelle adresse
        $this->client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#securite', $newEmail);
    }

    public function testCannotTakeAnotherUsersEmail(): void
    {
        $user = $this->createUser('mot-de-passe-actuel');
        $other = $this->createUser();
        $this->loginAs($user);

        $this->submitProfileForm('Recevoir un code', ['newEmail' => $other->getEmail(), 'currentPassword' => 'mot-de-passe-actuel']);
        $this->assertEmailCount(0);
        $this->assertQueuedEmailCount(0);
    }

    public function testExportContainsOwnDataOnly(): void
    {
        $user = $this->createUser();
        $other = $this->createUser();

        $this->loginAs($other);
        $this->client->jsonRequest('POST', '/api/journal/entries/create', ['content' => 'Secret de l\'autre utilisateur']);
        $this->loginAs($user);
        $this->client->jsonRequest('POST', '/api/journal/entries/create', ['content' => 'Ma réflexion du jour']);

        $this->client->request('GET', '/profil/export');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');
        $this->assertStringContainsString('attachment', $this->client->getResponse()->headers->get('content-disposition'));

        $json = $this->client->getResponse()->getContent();
        $data = json_decode($json, true);
        $this->assertSame($user->getEmail(), $data['compte']['email']);
        $this->assertStringContainsString('Ma réflexion du jour', $json);
        $this->assertStringNotContainsString('Secret de l\'autre utilisateur', $json);
        $this->assertStringNotContainsString('$2y$', $json, 'Aucune empreinte de mot de passe');
        $this->assertStringNotContainsString('"password"', $json);
    }

    public function testDeleteRequiresConfirmationAndPassword(): void
    {
        $user = $this->createUser('mot-de-passe-actuel');
        $this->loginAs($user);

        $this->submitProfileForm('Supprimer définitivement', ['confirmation' => 'oui', 'currentPassword' => 'mot-de-passe-actuel']);
        $this->assertNotNull($this->findUser($user->getEmail()));

        $this->submitProfileForm('Supprimer définitivement', ['confirmation' => 'SUPPRIMER', 'currentPassword' => 'faux']);
        $this->assertNotNull($this->findUser($user->getEmail()));
    }

    /**
     * Suppression d'un compte rempli par app:seed-data (plans, rituels, tests, journal…) :
     * plus aucune ligne de la base ne doit référencer l'utilisateur.
     */
    public function testDeleteRemovesEveryTraceOfTheUser(): void
    {
        $email = $this->uniqueEmail('suppression');
        $tester = new CommandTester((new Application(static::$kernel))->find('app:seed-data'));
        $tester->execute(['email' => $email, 'password' => 'mot-de-passe-actuel']);
        $user = $this->findUser($email);
        $this->assertNotNull($user);
        $user->setIsVerified(true);
        $this->em()->flush();
        $userId = $user->getId();
        $this->assertGreaterThan(10, $this->countRowsLinkedTo($userId), 'Le compte de test doit contenir des données');

        $this->loginAs($user);
        $this->submitProfileForm('Supprimer définitivement', ['confirmation' => 'supprimer', 'currentPassword' => 'mot-de-passe-actuel']);

        $this->assertResponseRedirects('/login');
        $this->assertSame('Votre compte KIINI SAWA a été supprimé', $this->lastEmail()->getSubject());
        $this->assertNull($this->findUser($email));
        $this->assertSame(0, $this->countRowsLinkedTo($userId), 'Aucune donnée ne doit survivre à la suppression');

        // La session est fermée
        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/login');
    }

    /** Nombre de lignes, toutes tables confondues, qui pointent directement vers cet utilisateur. */
    private function countRowsLinkedTo(int $userId): int
    {
        $conn = $this->em()->getConnection();
        $fks = $conn->fetchAllAssociative(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'user'"
        );
        $total = 0;
        foreach ($fks as $fk) {
            $total += (int) $conn->fetchOne(sprintf('SELECT COUNT(*) FROM `%s` WHERE `%s` = ?', $fk['TABLE_NAME'], $fk['COLUMN_NAME']), [$userId]);
        }

        return $total;
    }
}
