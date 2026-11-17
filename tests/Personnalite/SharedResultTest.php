<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Résultat partageable : privé par défaut ; une fois partagé, page publique avec le prénom et le résultat principal
 * seulement ; partage révocable.
 */
class SharedResultTest extends WebTestCase
{
    use AccountTestTrait;

    public function testShareThenStopSharing(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $profile = $user->getUserProfile() ?? (new \App\Compte\Entity\UserProfile())->setUser($user);
        $profile->setFirstName('Priscille')->setLastName('Secret');
        $this->em()->persist($profile);
        $this->em()->flush();
        $answers = [];
        foreach (Instruments::items('disc') as $item) {
            $answers[$item['key']] = 'IC';
        }
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $answers, 400);
        $client->loginUser($user);

        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertSelectorTextContains('#partage', 'Partager mon résultat');
        $this->assertNull($a->getShareToken(), 'Privé par défaut');

        $client->submitForm('Partager mon résultat');
        $token = $this->em()->getRepository(\App\Personnalite\Entity\Assessment::class)->find($a->getId())->getShareToken();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{24}$/', $token);

        $client->request('GET', '/logout');
        $client->request('GET', '/resultat/'.$token);
        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        $this->assertStringContainsString('Priscille', $html);
        $this->assertStringNotContainsString('Secret', $html, 'Le nom de famille reste privé');
        $this->assertStringNotContainsString('vigilance', mb_strtolower($html), 'Pas de détail du rapport');
        $this->assertSelectorExists('meta[property="og:title"]');

        $client->loginUser($user);
        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $client->submitForm('Arrêter le partage');
        $client->request('GET', '/logout');
        $client->request('GET', '/resultat/'.$token);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testOthersCannotShareMyResult(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $answers = [];
        foreach (Instruments::items('type') as $i => $item) {
            $answers[$item['key']] = 1 + ($i % 5);
        }
        $a = static::getContainer()->get(AssessmentService::class)->record($owner, 'type', $answers, 300);
        $client->loginUser($this->createUser());
        $client->request('POST', '/mes-tests/rapport/'.$a->getId().'/partage', ['_csrf_token' => 'x']);
        $this->assertResponseStatusCodeSame(404);
    }
}
