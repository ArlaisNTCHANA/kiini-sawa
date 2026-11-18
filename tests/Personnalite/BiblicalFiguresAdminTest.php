<?php

namespace App\Tests\Personnalite;

use App\Compte\Repository\UserRepository;
use App\Personnalite\Entity\ContentOverride;
use App\Personnalite\Psychometrie\BiblicalFigures;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Psychometrie\DiscBank;
use App\Personnalite\Psychometrie\Instruments;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Les fiches de personnages bibliques se modifient depuis Admin › Textes des tests › Personnages bibliques :
 * les textes changent dans les rapports, les citations de la Louis Segond et les noms de rattachement restent verrouillés,
 * et la mention « en cours de relecture » disparaît quand le fondateur passe la relecture à « faite ».
 */
class BiblicalFiguresAdminTest extends WebTestCase
{
    use AccountTestTrait;

    protected function tearDown(): void
    {
        Content::setLoader(null);
        parent::tearDown();
    }

    public function testCitationsAndMatchingNamesAreLocked(): void
    {
        $this->assertTrue(Content::isEditable('figures.library', 'JOSEPH.grace'));
        $this->assertTrue(Content::isEditable('figures.library', 'JOSEPH.profile.dimensions.S.traits.0.1'));
        $this->assertTrue(Content::isEditable('figures.library', 'JOSEPH.relecture'));
        $this->assertFalse(Content::isEditable('figures.library', 'JOSEPH.quotes.0.text'), 'Les citations de la LSG ne se modifient pas');
        $this->assertFalse(Content::isEditable('figures.library', 'JOSEPH.episodes.0.quote.text'));
        $this->assertFalse(Content::isEditable('figures.library', 'JOSEPH.aliases.0'), 'Le nom de rattachement aux profils ne se modifie pas');
        $this->assertFalse(Content::isEditable('figures.library', 'JOSEPH.profile.code'));
        $this->assertFalse(Content::isEditable('figures.library', 'JOSEPH.episodes.0.tags.0.1'));
    }

    public function testEditAFigureThenSeeItInTheReport(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        // Profil SC : Joseph de Nazareth et Timothée
        $answers = [];
        foreach (Instruments::items('disc') as $i => $item) {
            $answers[$item['key']] = ['S', 'S', 'C'][$i % 3].['D', 'I'][$i % 2];
        }
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $answers, 400);
        $this->assertSame('S', $a->getScores()['primary']);

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/tests/textes');
        $this->assertSelectorTextContains('main', 'Personnages bibliques');
        $client->request('GET', '/admin/tests/textes/figures.library');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'JOSEPH');

        $crawler = $client->request('GET', '/admin/tests/textes/figures.library/JOSEPH');
        $this->assertResponseIsSuccessful();
        $html = $client->getResponse()->getContent();
        $this->assertStringNotContainsString(base64_encode('JOSEPH.quotes.0.text'), $html, 'Pas de champ pour une citation biblique');
        $form = $crawler->selectButton('Enregistrer')->form([
            'f['.base64_encode('JOSEPH.grace').']' => 'Texte de grâce réécrit par le fondateur.',
            'f['.base64_encode('JOSEPH.relecture').']' => 'faite',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects();
        $this->assertNotNull($this->em()->getRepository(ContentOverride::class)->findOneBy(['path' => 'figures.library.JOSEPH.grace']));

        // Le rapport de l'utilisateur affiche le texte modifié ; Joseph n'est plus « en cours de relecture », Timothée l'est encore
        Content::reset();
        $this->assertSame('Texte de grâce réécrit par le fondateur.', BiblicalFigures::find('Joseph de Nazareth')['grace']);
        $this->assertSame('faite', BiblicalFigures::find('Joseph de Nazareth')['relecture']);
        $this->assertSame('en cours', BiblicalFigures::find('Timothée')['relecture']);
        $client->loginUser(static::getContainer()->get(UserRepository::class)->find($user->getId()));
        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Texte de grâce réécrit par le fondateur.', $client->getResponse()->getContent());
    }
}
