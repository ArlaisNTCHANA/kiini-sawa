<?php

namespace App\Tests\Personnalite;

use App\Compte\Repository\UserRepository;
use App\Personnalite\Entity\ContentOverride;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Content;
use App\Personnalite\Psychometrie\DiscCombos;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Textes des tests modifiables depuis l'administration : le rapport et le questionnaire affichent le nouveau texte,
 * le calcul et la structure restent verrouillés, et l'on peut revenir au texte d'origine.
 */
class ContentAdminTest extends WebTestCase
{
    use AccountTestTrait;

    protected function tearDown(): void
    {
        Content::setLoader(null);
        parent::tearDown();
    }

    public function testEditReportTextThenRevert(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $answers = [];
        foreach (Instruments::items('disc') as $item) {
            $answers[$item['key']] = 'IS';
        }
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $answers, 400);
        $code = $a->getScores()['primary'].($a->getScores()['secondary'] ?? '');

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/tests/textes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Les 16 profils');
        $this->assertSelectorTextNotContains('main', 'IPIP-NEO :');

        $crawler = $client->request('GET', '/admin/tests/textes/disc.profiles/'.$code);
        $this->assertResponseIsSuccessful();
        $field = 'f['.base64_encode($code.'.portrait').']';
        $form = $crawler->selectButton('Enregistrer')->form([$field => 'Portrait réécrit par le fondateur.']);
        $client->submit($form);
        $this->assertResponseRedirects();
        $this->assertNotNull($this->em()->getRepository(ContentOverride::class)->findOneBy(['path' => 'disc.profiles.'.$code.'.portrait']));

        // Le rapport de l'utilisateur affiche le nouveau texte
        $client->loginUser(static::getContainer()->get(UserRepository::class)->find($user->getId()));
        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertSelectorTextContains('main', 'Portrait réécrit par le fondateur.');

        // Retour au texte d'origine
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $crawler = $client->request('GET', '/admin/tests/textes/disc.profiles/'.$code);
        $form = $crawler->selectButton('Enregistrer')->form();
        $form['reset['.base64_encode($code.'.portrait').']']->tick();
        $client->submit($form);
        $this->assertNull($this->em()->getRepository(ContentOverride::class)->findOneBy(['path' => 'disc.profiles.'.$code.'.portrait']));
        $client->loginUser(static::getContainer()->get(UserRepository::class)->find($user->getId()));
        $client->request('GET', '/mes-tests/rapport/'.$a->getId());
        $this->assertSelectorTextContains('main', mb_substr(DiscCombos::PROFILES[$code]['portrait'], 0, 30));
    }

    public function testStructureStaysLocked(): void
    {
        $this->assertFalse(Content::isEditable('enn.items', '1.0'), 'Le type d\'une affirmation ne se modifie pas');
        $this->assertTrue(Content::isEditable('enn.items', '1.1'));
        $this->assertFalse(Content::isEditable('gifts.items', '1.0'), 'Le don mesuré ne se modifie pas');
        $this->assertFalse(Content::isEditable('disc.dimensions', 'D.color'));
        $this->assertFalse(Content::isEditable('enn.profiles', '1.center'));
        $this->assertFalse(Content::isEditable('disc.profiles', 'ZZ.portrait'), 'Chemin inexistant');
        $this->assertArrayNotHasKey('ipip.items', Content::SOURCES, 'Questions IPIP verrouillées');
        foreach (array_keys(Content::SOURCES) as $source) {
            $this->assertNotEmpty(Content::leaves($source), $source);
        }
    }

    public function testQuestionWordingOverride(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $em->persist(new ContentOverride('disc.words.1.0', 'Résolu à agir', null));
        $em->flush();
        $client->loginUser($this->createUser());
        $client->request('GET', '/mes-tests/questionnaire/disc');
        $this->assertSelectorTextContains('main', 'Résolu à agir');
        $this->assertSelectorTextNotContains('main', 'Décidé');
        $em = $this->em();
        $em->remove($em->getRepository(ContentOverride::class)->findOneBy(['path' => 'disc.words.1.0']));
        $em->flush();
    }

    public function testNonAdminCannotEdit(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser());
        $client->request('GET', '/admin/tests/textes');
        $this->assertResponseStatusCodeSame(403);
    }
}
