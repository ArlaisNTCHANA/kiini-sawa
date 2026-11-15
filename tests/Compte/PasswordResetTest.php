<?php

namespace App\Tests\Compte;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Mot de passe oublié.
 */
class PasswordResetTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function requestReset(string $email): void
    {
        $crawler = $this->client->request('GET', '/mot-de-passe/oublie');
        $this->client->submit($crawler->selectButton('Envoyer le lien')->form(), ['email' => $email]);
    }

    private function resetLinkFrom(string $html): string
    {
        $this->assertMatchesRegularExpression('#http://localhost(/mot-de-passe/reinitialiser/[a-f0-9]{24}/[a-f0-9]{40})#', $html);
        preg_match('#http://localhost(/mot-de-passe/reinitialiser/[a-f0-9]{24}/[a-f0-9]{40})#', $html, $m);

        return $m[1];
    }

    public function testUnknownEmailGetsTheSameAnswerAndNoEmail(): void
    {
        $this->requestReset('personne-'.uniqid().'@exemple.org');

        $this->assertResponseRedirects('/login');
        $this->assertEmailCount(0);
        $this->assertQueuedEmailCount(0);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Si un compte est associé');
    }

    public function testFullResetJourney(): void
    {
        $user = $this->createUser('ancien-mot-de-passe', verified: false);
        $this->requestReset(mb_strtoupper($user->getEmail()));
        $this->assertResponseRedirects('/login');

        $email = $this->lastEmail();
        $this->assertSame([$user->getEmail()], array_map(fn ($a) => $a->getAddress(), $email->getTo()));
        $link = $this->resetLinkFrom($email->getHtmlBody() ?? $this->renderedHtml($email));

        // Le jeton est retiré de l'URL dès la première visite
        $this->client->request('GET', $link);
        $this->assertResponseRedirects('/mot-de-passe/reinitialiser');
        $crawler = $this->client->followRedirect();

        // Mot de passe trop court refusé
        $this->client->submit($crawler->selectButton('Enregistrer')->form(), ['password' => 'court']);
        $this->assertResponseStatusCodeSame(422);

        $crawler = $this->client->request('GET', '/mot-de-passe/reinitialiser');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(), ['password' => 'nouveau mot de passe solide']);
        $this->assertResponseRedirects('/login');

        $updated = $this->findUser($user->getEmail());
        $hasher = static::getContainer()->get('security.user_password_hasher');
        $this->assertTrue($hasher->isPasswordValid($updated, 'nouveau mot de passe solide'));
        $this->assertTrue($updated->isVerified(), 'Recevoir le lien prouve la possession de l\'adresse');

        // Le lien ne sert qu'une fois
        $this->client->request('GET', $link);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Lien expiré');
    }

    public function testForgedLinkIsRejected(): void
    {
        $user = $this->createUser();
        $this->requestReset($user->getEmail());
        $link = $this->resetLinkFrom($this->renderedHtml($this->lastEmail()));
        $forged = substr($link, 0, -1).('0' === substr($link, -1) ? '1' : '0');

        $this->client->request('GET', $forged);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Lien expiré');
    }

    private function renderedHtml(\Symfony\Component\Mime\Email $email): string
    {
        if (null !== $email->getHtmlBody()) {
            return $email->getHtmlBody();
        }
        $twig = static::getContainer()->get('twig');

        return $twig->render($email->getHtmlTemplate(), $email->getContext());
    }
}
