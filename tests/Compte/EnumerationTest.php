<?php

namespace App\Tests\Compte;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Audit de sécurité, lot 4 : on ne révèle pas si une adresse e-mail a déjà un compte (inscription, changement d'adresse). */
class EnumerationTest extends WebTestCase
{
    use AccountTestTrait;

    private function register(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): void
    {
        $form = $client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Test', 'email' => $email, 'password' => 'une phrase assez longue']);
    }

    public function testExistingAndNewAddressesGetTheSameRedirectAndTheSameScreen(): void
    {
        $a = static::createClient();
        $existing = $this->createUser();
        $this->register($a, $existing->getEmail());
        $existingRedirect = $a->getResponse()->headers->get('Location');
        $existingPage = $a->followRedirect();
        $existingTitle = $existingPage->filter('h1')->text();
        $existingFields = $existingPage->filter('input')->count();

        $a->restart();
        $b = $a;
        $new = $this->uniqueEmail('nouveau');
        $this->register($b, $new);
        $newRedirect = $b->getResponse()->headers->get('Location');
        $newPage = $b->followRedirect();

        $this->assertSame($newRedirect, $existingRedirect, 'même redirection');
        $this->assertSame($newPage->filter('h1')->text(), $existingTitle, 'même titre');
        $this->assertSame($newPage->filter('input')->count(), $existingFields, 'même formulaire');
        $this->assertStringContainsString($existing->getEmail(), $existingPage->text(), 'l\'adresse saisie est rappelée, comme pour un nouvel inscrit');
        $this->assertSame(1, $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM `user` WHERE email = ?', [$existing->getEmail()]), 'aucun doublon');
    }

    public function testTheDecoyScreenNeverAcceptsACodeAndNeverLogsAnyoneIn(): void
    {
        $client = static::createClient();
        $existing = $this->createUser();
        $this->register($client, $existing->getEmail());
        $client->followRedirect();

        $client->submitForm('Valider', ['code' => '123456']);
        $this->assertResponseRedirects('/verification-email');
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Code incorrect ou expiré');

        // Pas connecté : une page protégée renvoie à la connexion
        $client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/login');
    }

    public function testNobodyElseCanUseTheVerificationScreen(): void
    {
        $client = static::createClient();
        $client->request('GET', '/verification-email');
        $this->assertResponseRedirects('/login', null, 'sans session de vérification : retour à la connexion');
        $client->request('POST', '/verification-email/renvoyer', ['_csrf_token' => 'x']);
        $this->assertResponseRedirects('/login');
    }

    public function testChangingToAnExistingAddressLooksTheSameAsChangingToAFreeOne(): void
    {
        $client = static::createClient();
        $other = $this->createUser();
        $user = $this->createUser();
        $user2 = $this->createUser();

        $client->loginUser($user);
        $client->request('POST', '/profil/email', ['_csrf_token' => 'jeton-de-test', 'currentPassword' => 'mot-de-passe-solide', 'newEmail' => $other->getEmail()]);
        $takenFlash = $client->getRequest()->getSession()->getFlashBag()->all();
        $this->assertQueuedEmailCount(0);

        $client->restart();
        $client->loginUser($user2);
        $client->request('POST', '/profil/email', ['_csrf_token' => 'jeton-de-test', 'currentPassword' => 'mot-de-passe-solide', 'newEmail' => $this->uniqueEmail('libre')]);
        $freeFlash = $client->getRequest()->getSession()->getFlashBag()->all();
        $this->assertQueuedEmailCount(1);

        $this->assertSame(['success'], array_keys($takenFlash));
        $this->assertSame(['success'], array_keys($freeFlash));
        $this->assertStringContainsString('si cette adresse peut être utilisée', $takenFlash['success'][0]);
        $this->assertStringContainsString('si cette adresse peut être utilisée', $freeFlash['success'][0]);
    }

    public function testTheCooldownIsTheSameWhateverTheAddress(): void
    {
        $client = static::createClient();
        $other = $this->createUser();
        $client->loginUser($this->createUser());
        $client->request('POST', '/profil/email', ['_csrf_token' => 'jeton-de-test', 'currentPassword' => 'mot-de-passe-solide', 'newEmail' => $other->getEmail()]);
        $client->request('POST', '/profil/email', ['_csrf_token' => 'jeton-de-test', 'currentPassword' => 'mot-de-passe-solide', 'newEmail' => $other->getEmail()]);
        $flash = $client->getRequest()->getSession()->getFlashBag()->all();
        $this->assertStringContainsString('Patientez une minute', implode(' ', $flash['error'] ?? []), 'redemander tout de suite : même attente qu\'avec une adresse libre');
    }
}
