<?php

namespace App\Tests\PlanDeVie;

use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\LifeMilestone;
use App\PlanDeVie\Entity\PlanDeVie;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Retour du fondateur (26/11/2026) : « quand j'imprime le Plan de Vie, je ne vois pas tous les détails ».
 * L'en-tête et le pied fixes recouvraient le haut et le bas des pages, un message « envoyé avec succès » s'imprimait
 * sur chaque page, et les objectifs n'avaient ni priorité, ni progression, ni jalons.
 */
class PlanPrintViewTest extends WebTestCase
{
    use AccountTestTrait;

    public function testPrintViewShowsGoalDetailsAndNoFixedOverlays(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $vie = $user->getPlanDeVie() ?? (new PlanDeVie())->setUser($user);
        $goal = (new LifeGoal())->setPlanDeVie($vie)->setTitle('Animer un groupe de jeunes')->setCategory('DO')
            ->setPriority('HIGH')->setProgress(40)->setMetric('Une rencontre par mois');
        $step = (new LifeMilestone())->setLifeGoal($goal)->setTitle('Trouver une salle')->setDueDate(new \DateTime('2027-01-15'));
        foreach ([$vie, $goal, $step] as $e) {
            $this->em()->persist($e);
        }
        $this->em()->flush();
        $this->em()->clear();

        $client->loginUser($this->findUser((string) $user->getEmail()));
        $crawler = $client->request('GET', '/plan-de-vie/visualiser');
        $this->assertResponseIsSuccessful();

        $text = $crawler->filter('body')->text();
        foreach (['Haute priorité', 'Progression : 40 %', 'Mesure : Une rencontre par mois', 'Trouver une salle', '15/01/2027'] as $t) {
            $this->assertStringContainsString($t, $text);
        }

        // À l'impression, rien n'est en position fixe (cela recouvrait le contenu à partir de la 2e page)
        $html = (string) $client->getResponse()->getContent();
        $print = substr($html, strpos($html, '@media print'), 2500);
        $this->assertStringNotContainsString('position: fixed', $print);
        $this->assertStringContainsString('.toast-notify', $print);
    }
}
