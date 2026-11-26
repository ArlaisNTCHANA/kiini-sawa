<?php

namespace App\Tests\Personnalite;

use App\Personnalite\Entity\ProfileTest;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Rapports illustrés des auto-évaluations : roue, priorités, domaines, plan, PDF, conditions d'utilisation.
 */
class SelfAssessmentReportTest extends WebTestCase
{
    use AccountTestTrait;

    public function testEquilibreAndHygieneReports(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true); // les rapports PDF des tests sont réservés à PRO
        $em = $this->em();
        $em->persist((new ProfileTest())->setUser($user)->setType('EQUILIBRE')->setDate(new \DateTime())->setInsights('')
            ->setResult(json_encode(['sante' => 3, 'famille' => 8, 'finances' => 5, 'carriere' => 6, 'spiritualite' => 9, 'social' => 7, 'personnel' => 4, 'loisirs' => 2])));
        $em->persist((new ProfileTest())->setUser($user)->setType('HYGIENE')->setDate(new \DateTime())->setInsights('')
            ->setResult(json_encode(['axes' => ['physique' => 4.5, 'mental' => 6, 'relationnel' => 8, 'spirituel' => 7.5, 'ressources' => 3, 'environnement' => 6],
                'details' => ['physique' => ['Sommeil & Énergie' => 3, 'Hydratation' => 6]]])));
        $em->flush();
        $client->loginUser($user);

        $client->request('GET', '/mes-tests/equilibre/resultats');
        $this->assertResponseIsSuccessful();
        foreach (['Votre roue de la vie', 'Vos trois priorités', 'Repos et loisirs', 'Votre plan pour les 30 prochains jours', 'Bon usage de ce rapport', 'Une photographie du moment'] as $t) {
            $this->assertSelectorTextContains('main', $t);
        }
        $client->request('GET', '/mes-tests/equilibre/resultats/pdf');
        $this->assertResponseHeaderSame('content-type', 'application/pdf');

        $client->request('GET', '/mes-tests/hygiene/resultats');
        $this->assertResponseIsSuccessful();
        foreach (['Votre roue de vitalité', 'Trois habitudes à adopter', 'Pas un avis médical'] as $t) {
            $this->assertSelectorTextContains('main', $t);
        }
        // Le nombre de questions vient de la base (modifiable) : le rapport n'en annonce pas un en dur (il disait 24 pour 30)
        $this->assertStringNotContainsString('vingt-quatre', $client->getResponse()->getContent());
        $this->assertStringNotContainsString('24 habitudes', $client->getResponse()->getContent());
        $client->request('GET', '/mes-tests/hygiene/resultats/pdf');
        $this->assertResponseHeaderSame('content-type', 'application/pdf');
    }
}
