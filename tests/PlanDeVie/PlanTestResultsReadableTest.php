<?php

namespace App\Tests\PlanDeVie;

use App\Personnalite\Entity\ProfileTest;
use App\PlanDeVie\Service\PlanDataProvider;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Parcours du 26/11/2026 : le PDF du Plan de Vie affichait le résultat brut des tests ({"D":34,…}, et pour l'Hygiène de
 * Vie un long bloc illisible). Le Plan de Vie doit donner un résumé lisible, et lire les notes de l'Hygiène sous « axes ».
 */
class PlanTestResultsReadableTest extends KernelTestCase
{
    use AccountTestTrait;

    public function testScoredResultsAreSummarisedAndHygieneAxesAreRead(): void
    {
        self::bootKernel();
        $user = $this->createUser();
        $results = [
            'DISC' => '{"D":34,"I":0,"S":33,"C":33}',
            'HYGIENE' => '{"axes":{"physique":5.3,"mental":5,"spirituel":6},"details":{"physique":{"Sommeil & Énergie":2.5}}}',
            'ENNEAGRAM' => '{"type":3,"wing":4}',
        ];
        foreach ($results as $type => $result) {
            $this->em()->persist((new ProfileTest())->setUser($user)->setType($type)->setResult($result)->setDate(new \DateTime()));
        }
        $this->em()->flush();
        $this->em()->clear();

        $tests = static::getContainer()->get(PlanDataProvider::class)->getLifePlanData($this->findUser((string) $user->getEmail()))['latestTests'];

        foreach ($results as $type => $_) {
            $this->assertStringNotContainsString('{', (string) $tests[$type]['result'], $type);
        }
        $this->assertSame('Dominant (D) 34 % · Stable (S) 33 % · Conforme (C) 33 %', $tests['DISC']['result']);
        $this->assertSame(['physique' => 53.0, 'mental' => 50.0, 'spirituel' => 60.0], array_column($tests['HYGIENE']['chartData'], 'value', 'key'));
        $this->assertSame('3, aile 4', $tests['ENNEAGRAM']['result']);
    }
}
