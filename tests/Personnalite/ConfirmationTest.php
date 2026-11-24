<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Entity\ConfirmationRequest;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Confirmation des résultats par les proches : lien personnel, formulaire public sans compte, anonymat (3 réponses),
 * lecture dans le rapport, fermeture du lien.
 */
class ConfirmationTest extends WebTestCase
{
    use AccountTestTrait;

    public function testDiscConfirmedByThreeRelatives(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $answers = [];
        foreach (Instruments::items('disc') as $item) {
            $answers[$item['key']] = 'SD';
        }
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $answers, 400);
        $primary = $a->getScores()['primary'];

        $client->loginUser($user);
        $crawler = $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertSelectorTextContains('#regard-proches', 'Demander à mes proches de confirmer');
        $client->submit($crawler->filter('#regard-proches form')->form());
        $token = $this->em()->getRepository(ConfirmationRequest::class)->findOneBy(['user' => $user->getId()])->getToken();

        // Le propriétaire ne répond pas à sa propre demande
        $client->request('GET', '/confirmer/'.$token);
        $this->assertResponseRedirects('/mes-tests/');

        $client->request('POST', '/logout', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE]);
        // Réponse invalide : deux fois la même description
        $crawler = $client->request('GET', '/confirmer/'.$token);
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextNotContains('main', 'Dominant');
        $client->submit($crawler->selectButton('Envoyer mon regard')->form(), ['most' => 'S', 'least' => 'S']);
        $this->assertResponseStatusCodeSame(422);

        for ($i = 0; $i < 3; ++$i) {
            $client->getCookieJar()->clear();
            $crawler = $client->request('GET', '/confirmer/'.$token);
            $client->submit($crawler->selectButton('Envoyer mon regard')->form(), ['most' => $primary, 'least' => 'I' === $primary ? 'C' : 'I', 'relationship' => 'ami']);
            // Redirection après l'envoi : sans elle, Turbo laissait le formulaire affiché (la personne croyait que rien n'était parti)
            $this->assertResponseRedirects('/confirmer/'.$token.'/merci');
            $client->followRedirect();
            $this->assertSelectorTextContains('h1', 'Merci');
            $this->assertSelectorTextContains('main', 'Découvrir KIINI SAWA');
        }
        // Revenir sur le lien après avoir répondu mène au remerciement ; sans réponse, le remerciement renvoie au formulaire
        $client->request('GET', '/confirmer/'.$token);
        $this->assertResponseRedirects('/confirmer/'.$token.'/merci');
        $client->getCookieJar()->clear();
        $client->request('GET', '/confirmer/'.$token.'/merci');
        $this->assertResponseRedirects('/confirmer/'.$token);

        $client->loginUser(static::getContainer()->get(\App\Compte\Repository\UserRepository::class)->find($user->getId()));
        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertSelectorTextContains('#regard-proches', '3 proches ont répondu');
        $this->assertSelectorTextContains('#regard-proches', 'votre nature se voit');

        // Fermeture : le lien ne répond plus
        $crawler = $client->getCrawler();
        $client->submit($crawler->filter('#regard-proches form button:contains("Fermer le lien")')->form());
        $client->request('POST', '/logout', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE]);
        $client->request('GET', '/confirmer/'.$token);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testGiftsDetailHiddenUnderThreeAnswers(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $answers = [];
        foreach (Instruments::items('dons-spirituels') as $i => $item) {
            $answers[$item['key']] = 1 + ($i % 5);
        }
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'dons-spirituels', $answers, 400);
        $request = static::getContainer()->get(\App\Personnalite\Service\ConfirmationService::class)->open($user, 'dons-spirituels');

        $crawler = $client->request('GET', '/confirmer/'.$request->getToken());
        $form = $crawler->selectButton('Envoyer mon regard')->form();
        foreach ($form['gifts'] as $field) {
            if (\in_array($field->availableOptionValues()[0], ['HOSPITALITE', 'SERVICE'], true)) {
                $field->tick();
            }
        }
        $client->submit($form);
        $client->followRedirect();
        $this->assertSelectorTextContains('h1', 'Merci');

        $client->loginUser($user);
        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertSelectorTextContains('#regard-proches', 'à partir de 3 réponses');
        $this->assertSelectorTextNotContains('#regard-proches', 'Confirmés par vos proches');
    }
}
