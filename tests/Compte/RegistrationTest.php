<?php

namespace App\Tests\Compte;

use App\Compte\Entity\AccountToken;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Inscription, vérification de l'email par code, onboarding.
 */
class RegistrationTest extends WebTestCase
{
    use AccountTestTrait;

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    private function register(array $override = []): void
    {
        $crawler = $this->client->request('GET', '/inscription');
        $form = $crawler->selectButton('Créer mon compte')->form();
        $values = array_merge([
            'firstName' => 'Esther',
            'email' => $this->uniqueEmail('inscription'),
            'password' => 'une phrase assez longue',
            'acceptTerms' => true,
        ], $override);
        if (!$values['acceptTerms']) {
            $form['acceptTerms']->untick();
            unset($values['acceptTerms']);
        } else {
            $form['acceptTerms']->tick();
            unset($values['acceptTerms']);
        }
        $this->client->submit($form, $values);
    }

    public function testFullJourneyFromRegistrationToLifePlan(): void
    {
        $email = $this->uniqueEmail('parcours');
        $this->register(['email' => mb_strtoupper($email)]);

        // Compte créé, non vérifié, consentement horodaté, prénom enregistré
        $this->assertResponseRedirects('/verification-email');
        $code = $this->codeFrom($this->lastEmail());
        $user = $this->findUser($email);
        $this->assertNotNull($user, 'Email stocké en minuscules');
        $this->assertFalse($user->isVerified());
        $this->assertNotNull($user->getTermsAcceptedAt());
        $this->assertSame('Esther', $user->getUserProfile()->getFirstName());

        // Tant que l'email n'est pas vérifié, l'application est inaccessible
        $this->client->request('GET', '/dashboard');
        $this->assertResponseRedirects('/verification-email');
        $this->client->request('GET', '/api/journal/entries');
        $this->assertResponseStatusCodeSame(403);

        // Mauvais code
        $crawler = $this->client->request('GET', '/verification-email');
        $this->client->submit($crawler->selectButton('Valider')->form(), ['code' => '000000' === $code ? '111111' : '000000']);
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Code incorrect');

        // Bon code (les espaces sont tolérés)
        $crawler = $this->client->request('GET', '/verification-email');
        $this->client->submit($crawler->selectButton('Valider')->form(), ['code' => substr($code, 0, 3).' '.substr($code, 3)]);
        $this->assertResponseRedirects('/onboarding');
        $this->assertTrue($this->findUser($email)->isVerified());

        // Onboarding : prénom, raison d'être, 3 valeurs, un objectif
        $crawler = $this->client->request('GET', '/onboarding');
        $form = $crawler->filter('form.ob-card')->form();
        $this->client->request('POST', '/onboarding', [
            'firstName' => 'Esther',
            'purpose' => 'Servir ma génération avec sagesse et courage.',
            'values' => ['Foi', 'Famille', 'Service'],
            'goalTitle' => 'Écrire un livre sur la transmission',
            'goalCategory' => 'GIVE',
            'goalDeadline' => (new \DateTimeImmutable('+2 years'))->format('Y-m-d'),
            '_csrf_token' => $form->get('_csrf_token')->getValue(),
        ]);
        $this->assertResponseRedirects('/plan-de-vie?onglet=analysis');

        $user = $this->findUser($email);
        $plan = $user->getPlanDeVie();
        $this->assertTrue($user->isOnboardingCompleted());
        $this->assertSame('Servir ma génération avec sagesse et courage.', $plan->getPurpose()->getStatement());
        $this->assertCount(3, $plan->getValues());
        $this->assertCount(1, $plan->getLifeGoals());
        $this->assertSame('GIVE', $plan->getLifeGoals()->first()->getCategory());

        // Le premier moment de valeur : l'analyse de cohérence avec un score
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('#tab-analysis .analysis-score-badge', 'Score de cohérence');
    }

    public function testOnboardingCanBeSkipped(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/onboarding');
        $token = $crawler->filter('input[name=_csrf_token]')->attr('value');

        $this->client->request('POST', '/onboarding', ['skip' => '1', '_csrf_token' => $token]);
        $this->assertResponseRedirects('/dashboard');
        $this->assertTrue($this->findUser($user->getEmail())->isOnboardingCompleted());
    }

    public function testOnboardingRejectsIncompleteAnswers(): void
    {
        $user = $this->createUser();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/onboarding');

        $this->client->request('POST', '/onboarding', [
            'firstName' => 'Paul',
            'purpose' => 'Court',
            'values' => ['Foi', 'foi', ''],
            'goalTitle' => 'Un objectif',
            'goalCategory' => 'DO',
            'goalDeadline' => '2020-01-01',
            '_csrf_token' => $crawler->filter('input[name=_csrf_token]')->attr('value'),
        ]);
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.ob-card', 'au moins une phrase');
        $this->assertNull($this->findUser($user->getEmail())->getPlanDeVie());
    }

    public function testRegistrationRequiresConsentAndValidData(): void
    {
        $email = $this->uniqueEmail('refus');
        $this->register(['email' => $email, 'acceptTerms' => false, 'password' => 'court']);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('body', 'Vous devez accepter les conditions');
        $this->assertSelectorTextContains('body', 'au moins 10 caractères');
        $this->assertNull($this->findUser($email));
    }

    public function testRegistrationWithExistingEmailRevealsNothing(): void
    {
        $existing = $this->createUser();
        $this->register(['email' => $existing->getEmail()]);

        $this->assertResponseRedirects('/login');
        $this->assertSame('Vous avez déjà un compte KIINI SAWA', $this->lastEmail()->getSubject());
        $this->assertSame(1, $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM `user` WHERE email = ?', [$existing->getEmail()]));
    }

    public function testHoneypotBlocksBots(): void
    {
        $email = $this->uniqueEmail('robot');
        $this->register(['email' => $email, 'website' => 'http://spam.example']);

        $this->assertResponseRedirects('/login');
        $this->assertNull($this->findUser($email));
    }

    public function testCodeIsLockedAfterFiveWrongAttempts(): void
    {
        $email = $this->uniqueEmail('essais');
        $this->register(['email' => $email]);
        $code = $this->codeFrom($this->lastEmail());
        $wrong = '000000' === $code ? '111111' : '000000';

        for ($i = 0; $i < 5; ++$i) {
            $crawler = $this->client->request('GET', '/verification-email');
            $this->client->submit($crawler->selectButton('Valider')->form(), ['code' => $wrong]);
        }
        // Le bon code ne fonctionne plus : il faut en redemander un
        $crawler = $this->client->request('GET', '/verification-email');
        $this->client->submit($crawler->selectButton('Valider')->form(), ['code' => $code]);
        $this->assertFalse($this->findUser($email)->isVerified());

        // Le renvoi immédiat est bloqué (anti-spam)
        $crawler = $this->client->request('GET', '/verification-email');
        $this->client->submit($crawler->selectButton('Renvoyer un code')->form());
        $this->client->followRedirect();
        $this->assertSelectorTextContains('.alert', 'Patientez une minute');
    }

    public function testVerificationCodeIsNotStoredInClear(): void
    {
        $email = $this->uniqueEmail('hash');
        $this->register(['email' => $email]);
        $code = $this->codeFrom($this->lastEmail());

        $token = $this->em()->getRepository(AccountToken::class)->findOneBy(['user' => $this->findUser($email)]);
        $this->assertNotSame($code, $token->getSecretHash());
        $this->assertStringNotContainsString($code, $token->getSecretHash());
    }
}
