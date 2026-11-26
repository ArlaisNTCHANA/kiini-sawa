<?php

namespace App\Tests\Personnalite;

use App\Compte\Entity\User;
use App\Personnalite\Entity\PersonalityAnswer;
use App\Personnalite\Entity\PersonalityQuestion;
use App\Personnalite\Entity\ProfileTest;
use App\Tests\Compte\AccountTestTrait;
use App\Tests\Support\FixedCsrfTokenManager;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Auto-évaluations à échelle 1-10 (Équilibre de Vie, Hygiène de Vie) : la réponse choisie doit arriver
 * au serveur. Avant le 26/11/2026, un champ caché était répété à chaque bouton ; le dernier, vide,
 * écrasait la réponse et le rapport affichait 0/10 partout.
 */
class ScaleAnswersTest extends WebTestCase
{
    use AccountTestTrait;

    private const DOMAINS = ['sante', 'famille', 'finances', 'carriere', 'spiritualite', 'social', 'personnel', 'loisirs'];

    private KernelBrowser $client;
    private User $user;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = $this->em();
        if (0 === $em->getRepository(PersonalityQuestion::class)->count(['testType' => 'EQUILIBRE'])) {
            foreach (self::DOMAINS as $i => $domain) {
                $q = (new PersonalityQuestion())->setTestType('EQUILIBRE')->setCategory($domain)->setQuestion('Note pour '.$domain)->setSortOrder($i);
                for ($v = 1; $v <= 10; ++$v) {
                    $q->addAnswer((new PersonalityAnswer())->setText((string) $v)->setValue((string) $v)->setSortOrder($v));
                }
                $em->persist($q);
            }
            $em->flush();
        }
        $this->user = $this->createUser(pro: true);
        $this->client->loginUser($this->user);
    }

    protected function tearDown(): void
    {
        $em = $this->em();
        foreach ($em->getRepository(PersonalityQuestion::class)->findBy(['testType' => 'EQUILIBRE']) as $q) {
            $em->remove($q);
        }
        $em->flush();
        parent::tearDown();
    }

    private function tests(): array
    {
        return $this->em()->getRepository(ProfileTest::class)->findBy(['user' => $this->user->getId(), 'type' => 'EQUILIBRE']);
    }

    public function testEachScaleQuestionHasASingleHiddenField(): void
    {
        $crawler = $this->client->request('GET', '/mes-tests/equilibre/passer');
        $this->assertResponseIsSuccessful();
        foreach (self::DOMAINS as $domain) {
            $this->assertCount(1, $crawler->filter('input[type="hidden"][name="answers['.$domain.']"]'), $domain);
        }
    }

    public function testAnswersAreScoredAsGiven(): void
    {
        $answers = array_combine(self::DOMAINS, ['6', '8', '5', '7', '8', '4', '6', '4']);
        $this->client->request('POST', '/mes-tests/equilibre/passer', ['answers' => $answers, '_csrf_token' => FixedCsrfTokenManager::VALUE]);
        $this->assertResponseRedirects('/mes-tests/equilibre/resultats');

        $scores = json_decode($this->tests()[0]->getResult(), true);
        $this->assertSame(6, $scores['sante']);
        $this->assertSame(4, $scores['loisirs']);
    }

    public function testEmptyAnswersAreRefusedInsteadOfCountingAsZero(): void
    {
        $answers = array_fill_keys(self::DOMAINS, '');
        $answers['sante'] = '7';
        $this->client->request('POST', '/mes-tests/equilibre/passer', ['answers' => $answers, '_csrf_token' => FixedCsrfTokenManager::VALUE]);
        $this->assertResponseRedirects('/mes-tests/equilibre/passer');
        $this->assertCount(0, $this->tests(), 'Rien n\'est enregistré avec des réponses manquantes');
    }
}
