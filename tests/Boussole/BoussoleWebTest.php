<?php

namespace App\Tests\Boussole;

use App\Boussole\Entity\BoussoleResult;
use App\Boussole\Repository\BoussoleResultRepository;
use App\Boussole\Service\BoussoleEngine;
use App\Compte\Entity\User;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Boussole 360 sur le site : enregistrement dans le compte, visiteur qui crée son compte ensuite, isolement.
 */
final class BoussoleWebTest extends WebTestCase
{
    use AccountTestTrait;

    /** Réponses brutes qui donnent un profil contrasté : Vie au plus haut, Hygiène au plus bas. */
    private const ANSWERS = [5, 4, 4, 5, 4, 4, 1, 2, 2, 1, 2, 2, 5, 4, 4, 1, 4, 4, 1, 2, 2, 5, 2, 2];

    private function submit(KernelBrowser $client, mixed $answers, ?string $csrf = \App\Tests\Support\FixedCsrfTokenManager::VALUE): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];
        if (null !== $csrf) {
            $server['HTTP_X_CSRF_TOKEN'] = $csrf;
        }
        $client->request('POST', '/boussole/resultat', server: $server, content: json_encode(['reponses' => $answers]));

        return json_decode((string) $client->getResponse()->getContent(), true) ?? [];
    }

    /** @return list<BoussoleResult> */
    private function resultsOf(User $user): array
    {
        $this->em()->clear();

        return static::getContainer()->get(BoussoleResultRepository::class)->historyFor($this->findUser((string) $user->getEmail()));
    }

    public function testLoggedUserResultIsComputedOnTheServerAndSaved(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $json = $this->submit($client, self::ANSWERS);
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('no-store', (string) $client->getResponse()->headers->get('Cache-Control'));
        $this->assertTrue($json['enregistre']);

        $expected = (new BoussoleEngine())->evaluate(self::ANSWERS);
        $saved = $this->resultsOf($user);
        $this->assertCount(1, $saved);
        $this->assertSame(self::ANSWERS, $saved[0]->getAnswers());
        $this->assertSame($expected['scores'], $saved[0]->getScores());
        $this->assertSame($expected['coherence'], $saved[0]->getCoherence());
        $this->assertSame('HYGIENE', $saved[0]->getPriorityKey());
        $this->assertSame('web', $saved[0]->getSource());

        // Un double envoi immédiat (double clic) ne crée pas de doublon ; une autre passation, si
        $this->submit($client, self::ANSWERS);
        $this->submit($client, array_fill(0, 24, 3));
        $this->assertCount(2, $this->resultsOf($user));

        // Historique et dernière passation dans le compte, avec les vrais noms des plans
        $client->request('GET', '/boussole/historique');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.ks-header__title', 'Ma Boussole 360');
        $this->assertSelectorTextContains('body', 'Historique');
        $this->assertSelectorTextContains('body', 'Plan d\'Hygiène de vie');
        $this->assertSelectorTextNotContains('body', 'HYGIENE');

        $client->request('GET', '/mes-tests/synthese');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-boussole-card]', 'Ma Boussole 360');
    }

    public function testInvalidAnswersAreRefused(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        foreach ([array_fill(0, 23, 3), [...array_fill(0, 23, 3), 9], 'abc', null] as $bad) {
            $this->submit($client, $bad);
            $this->assertResponseStatusCodeSame(422);
        }
        $this->assertCount(0, $this->resultsOf($user));
    }

    public function testVisitorAnswersAreSavedAfterCreatingAnAccount(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boussole');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.cta-row', 'Créer mon compte gratuit');

        $json = $this->submit($client, self::ANSWERS);
        $this->assertFalse($json['enregistre']);
        $this->assertTrue($json['enAttente']);

        $email = $this->uniqueEmail('boussole');
        $form = $client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Esther', 'email' => $email, 'password' => 'une phrase assez longue', 'dateOfBirth' => '1990-05-12']);
        $code = $this->codeFrom($this->lastEmail());
        $client->followRedirect();
        $this->assertCount(0, $this->resultsOf($this->findUser($email)), 'Rien avant la vérification de l\'email');

        $client->submitForm('Valider', ['code' => $code]);
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Votre résultat de la Boussole 360 est enregistré dans votre compte.');

        $saved = $this->resultsOf($this->findUser($email));
        $this->assertCount(1, $saved);
        $this->assertSame(self::ANSWERS, $saved[0]->getAnswers());

        // La session est vidée : pas de second enregistrement
        $client->request('GET', '/boussole/historique');
        $this->assertCount(1, $this->resultsOf($this->findUser($email)));
    }

    public function testVisitorAnswersAreSavedAfterLoggingIn(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->submit($client, self::ANSWERS);

        $crawler = $client->request('GET', '/login');
        $client->submit($crawler->selectButton('Se connecter')->form(['email' => $user->getEmail(), 'password' => 'mot-de-passe-solide']));
        $client->followRedirect();

        $this->assertCount(1, $this->resultsOf($user));
    }

    public function testAPassationOfAnotherAccountIsNotVisible(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $other = $this->createUser();
        $client->loginUser($owner);
        $this->submit($client, self::ANSWERS);
        $id = $this->resultsOf($owner)[0]->getId();

        $client->request('GET', '/boussole/passation/'.$id);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'actions de stabilisation');

        $client->loginUser($this->findUser((string) $other->getEmail()));
        $client->request('GET', '/boussole/passation/'.$id);
        $this->assertResponseStatusCodeSame(404);
        $client->request('GET', '/boussole/historique');
        $this->assertSelectorTextContains('.ks-empty', 'pas encore de passation');
    }

    public function testSubmissionNeedsTheCsrfTokenAndIsLimited(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        // Sans jeton ou avec un faux jeton : refusé, rien d'enregistré
        $this->submit($client, self::ANSWERS, null);
        $this->assertResponseStatusCodeSame(403);
        $this->submit($client, self::ANSWERS, 'faux-jeton');
        $this->assertResponseStatusCodeSame(403);
        $this->assertCount(0, $this->resultsOf($user));

        // Au plus 10 passations par heure et par compte (réponses différentes : deux envois identiques coup sur coup ne
        // créent qu'une passation, BoussoleRecorder::DUPLICATE_WINDOW_SECONDS)
        for ($i = 0; $i < 10; ++$i) {
            $answers = self::ANSWERS;
            $answers[0] = ($i % 5) + 1;
            $answers[1] = (intdiv($i, 5) % 5) + 1;
            $this->submit($client, $answers);
            $this->assertResponseIsSuccessful();
        }
        $res = $this->submit($client, self::ANSWERS);
        $this->assertResponseStatusCodeSame(429);
        $this->assertStringContainsString('réessayer', $res['erreur']);
        $this->assertCount(10, $this->resultsOf($user));
    }

    public function testThePageGivesTheCsrfTokenToTheScript(): void
    {
        $client = static::createClient();
        $client->request('GET', '/boussole');
        $this->assertStringContainsString('X-CSRF-Token', (string) $client->getResponse()->getContent());
    }

    /** Le résultat ne promet que ce que l'offre gratuite donne vraiment (parcours « nouvel utilisateur » du 25/11). */
    public function testResultPromisesOnlyTheRealFreeOffer(): void
    {
        $client = static::createClient();
        $config = static::getContainer()->get(\App\Abonnement\Service\Entitlements::class)->config();
        $before = [$config->getTrialDurationDays(), $config->getFreeTests()];
        $config->setTrialDurationDays(14)->setFreeTests(['disc']);
        $this->em()->flush();

        try {
            $html = (string) $client->request('GET', '/boussole')->html();
            $this->assertResponseIsSuccessful();
            $this->assertStringNotContainsString('dans votre espace gratuit', $html);
            $this->assertStringNotContainsString('5 tests approfondis', $html);
            $this->assertStringContainsString('posez gratuitement votre Plan de Vie', $html);
            $this->assertStringContainsString('pendant 14 jours avec KIINI SAWA PRO', $html);
            $name = \App\Personnalite\Psychometrie\Instruments::CATALOG['disc']['name'];
            $this->assertStringContainsString('dont '.$name.' gratuitement', $html);
        } finally {
            $config->setTrialDurationDays($before[0])->setFreeTests($before[1]);
            $this->em()->flush();
        }
    }
}
