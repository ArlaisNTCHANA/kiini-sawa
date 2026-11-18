<?php

namespace App\Tests\Bilan;

use App\Bilan\Service\Rhythms;
use App\Compte\Entity\User;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** « Mes rythmes » : jour, semaine, mois, saison, année, avec ce qui est fait, à faire ou en retard. */
class RhythmsTest extends WebTestCase
{
    use AccountTestTrait;

    private function rhythms(User $user, string $when): array
    {
        $this->em()->clear();
        $list = static::getContainer()->get(Rhythms::class)->forUser($this->em()->find(User::class, $user->getId()), new \DateTimeImmutable($when));

        return array_column($list, null, 'key');
    }

    public function testFiveRhythmsInTheBooksOrder(): void
    {
        static::createClient();
        $user = $this->createUser(pro: true);
        $r = $this->rhythms($user, '2026-11-18 10:00');
        $this->assertSame(['day', 'week', 'month', 'season', 'year'], array_keys($r));
        foreach ($r as $item) {
            $this->assertContains($item['state'], ['done', 'todo', 'late', 'later']);
        }
    }

    public function testANewcomerIsNeverShownAsLate(): void
    {
        static::createClient();
        $user = $this->createUser(pro: true);
        foreach (['2026-11-18 10:00', '2026-11-18 21:00', '2026-11-22 10:00'] as $when) {
            foreach ($this->rhythms($user, $when) as $key => $item) {
                $this->assertNotSame('late', $item['state'], $key.' '.$when);
            }
        }
        $this->assertSame('todo', $this->rhythms($user, '2026-11-18 10:00')['season']['state'], 'Pas encore de saison : à commencer');
    }

    public function testDayAndWeekFollowTheClock(): void
    {
        static::createClient();
        $user = $this->createUser(pro: true);
        $this->assertSame('later', $this->rhythms($user, '2026-11-18 10:00')['day']['state']);
        $this->assertSame('todo', $this->rhythms($user, '2026-11-18 19:00')['day']['state']);
        $this->assertSame('later', $this->rhythms($user, '2026-11-18 10:00')['week']['state']);
        $this->assertSame('todo', $this->rhythms($user, '2026-11-22 10:00')['week']['state']);
        $this->assertSame('later', $this->rhythms($user, '2026-11-18 10:00')['month']['state']);
        $this->assertSame('todo', $this->rhythms($user, '2026-11-02 10:00')['month']['state'], 'Première semaine : revue du mois passé');
    }

    public function testThePageListsTheFiveRhythms(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $crawler = $client->request('GET', '/bilan/rythmes');
        $this->assertResponseIsSuccessful();
        $this->assertCount(5, $crawler->filter('[data-rhythm]'));
        $this->assertStringContainsString('/bilan/rythmes', $client->request('GET', '/bilan')->html());
    }

    public function testTheSeasonsFigureIsInteractive(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser(pro: true));
        $crawler = $client->request('GET', '/bilan/saison');
        $this->assertResponseIsSuccessful();
        $this->assertCount(4, $crawler->filter('[data-cycle-node]'));
    }

    /** Régression : un compte AVEC Plan d'Hygiène (le cas réel) : la lecture du bilan du jour ne doit pas échouer. */
    public function testDayRhythmWithAHygienePlan(): void
    {
        static::createClient();
        $user = $this->createUser(pro: true);
        $em = $this->em();
        $plan = (new \App\PlanDHygiene\Entity\PlanDHygiene())->setUser($user);
        $user->setPlanDHygiene($plan);
        $em->persist($plan);
        $em->flush();

        $this->assertSame('todo', $this->rhythms($user, '2026-11-18 19:00')['day']['state']);

        $planId = $plan->getId();
        $log = fn (string $d) => (new \App\PlanDHygiene\Entity\DailyLog())->setPlanDHygiene($this->em()->find(\App\PlanDHygiene\Entity\PlanDHygiene::class, $planId))->setDate(new \DateTime($d));
        $em = $this->em();
        $em->persist($log('2026-11-16'));
        $em->flush();
        $this->assertSame('late', $this->rhythms($user, '2026-11-18 10:00')['day']['state'], 'Habitué, mais hier manque');

        $em = $this->em();
        $em->persist($log('2026-11-17'));
        $em->persist($log('2026-11-18'));
        $em->flush();
        $this->assertSame('done', $this->rhythms($user, '2026-11-18 19:00')['day']['state']);

        // « Ma prochaine étape » lit le même bilan : même régression
        $next = static::getContainer()->get(\App\Dashboard\Service\NextStep::class);
        $this->assertNotSame('evening', $next->forUser($em->find(User::class, $user->getId()), new \DateTimeImmutable('2026-11-18 19:00'))['kind'], 'Bilan déjà fait');
    }
}
