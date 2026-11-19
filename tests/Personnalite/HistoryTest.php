<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Entity\Assessment;
use App\Personnalite\Entity\ProfileTest;
use App\Personnalite\Psychometrie\AssessmentService;
use App\Personnalite\Psychometrie\Instruments;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Historique des passations (« Vos passations ») et page « Mon évolution » (DISC, ennéagramme, équilibre de vie).
 */
class HistoryTest extends WebTestCase
{
    use AccountTestTrait;

    private function disc($user, string $pair, string $date): Assessment
    {
        $answers = [];
        foreach (Instruments::items('disc') as $item) {
            $answers[$item['key']] = $pair;
        }
        $a = static::getContainer()->get(AssessmentService::class)->record($user, 'disc', $answers, 400);
        $r = new \ReflectionProperty(Assessment::class, 'takenAt');
        $r->setValue($a, new \DateTimeImmutable($date));
        $this->em()->flush();

        return $a;
    }

    public function testDiscHistoryAndEvolution(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $old = $this->disc($user, 'DC', '-6 months');
        $new = $this->disc($user, 'SC', 'now');
        $client->loginUser($user);

        $client->request('GET', '/mes-tests/rapport/'.$new->getId());
        $this->assertSelectorTextContains('#passations', 'Vos passations');
        $this->assertCount(2, $client->getCrawler()->filter('#passations a[href^="/mes-tests/rapport/"]'));
        $this->assertSelectorTextContains('#passations', 'Voir mon évolution');

        // L'ancien rapport reste consultable, avec ses résultats du jour
        $client->request('GET', '/mes-tests/rapport/'.$old->getId());
        $this->assertResponseIsSuccessful();

        $client->request('GET', '/mes-tests/evolution/disc');
        $this->assertResponseIsSuccessful();
        foreach (['Ce qui a changé', 'Profil naturel', 'Profil adapté', 'Écart d\'adaptation', 'Toutes vos passations'] as $t) {
            $this->assertSelectorTextContains('main', $t);
        }

        $client->request('GET', '/mes-tests/evolution');
        $this->assertSelectorTextContains('main', 'DISC');
    }

    public function testEquilibreEvolutionAndPastResults(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true); // PDF des tests : PRO
        $em = $this->em();
        $mk = fn (array $s, string $d) => (new ProfileTest())->setUser($user)->setType('EQUILIBRE')->setDate(new \DateTime($d))->setInsights('')->setResult(json_encode($s));
        $first = $mk(['sante' => 3, 'famille' => 6, 'finances' => 4, 'carriere' => 5, 'spiritualite' => 6, 'social' => 5, 'personnel' => 4, 'loisirs' => 2], '-3 months');
        $em->persist($first);
        $em->persist($mk(['sante' => 7, 'famille' => 7, 'finances' => 4, 'carriere' => 5, 'spiritualite' => 8, 'social' => 5, 'personnel' => 5, 'loisirs' => 3], 'now'));
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/mes-tests/evolution/equilibre');
        $this->assertSelectorTextContains('main', 'Votre plus beau progrès : santé et vitalité');
        $this->assertSelectorTextContains('main', 'Avant et aujourd\'hui');

        $client->request('GET', '/mes-tests/equilibre/resultats/'.$first->getId());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Score global : 4,4 / 10');
        $client->request('GET', '/mes-tests/equilibre/resultats/pdf?id='.$first->getId());
        $this->assertResponseHeaderSame('content-type', 'application/pdf');

        // Les résultats d'un autre membre restent privés
        $client->loginUser($this->createUser());
        $client->request('GET', '/mes-tests/equilibre/resultats/'.$first->getId());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testSingleAttemptInvitesToRetake(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $this->disc($user, 'IS', 'now');
        $client->loginUser($user);
        $client->request('GET', '/mes-tests/evolution/disc');
        $this->assertSelectorTextContains('main', 'Une seule passation');
        $client->request('GET', '/mes-tests/evolution/inconnu');
        $this->assertResponseStatusCodeSame(404);
    }
}
