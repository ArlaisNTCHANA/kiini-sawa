<?php

namespace App\Tests\Dashboard;

use App\Compte\Entity\User;
use App\Dashboard\Service\NextStep;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * « Ma prochaine étape » : une seule action en tête de l'accueil, choisie par des règles ordonnées (fondations, bilan du soir,
 * revue du week-end, parcours, plan le moins avancé), plus deux « ensuite ».
 */
class NextStepTest extends WebTestCase
{
    use AccountTestTrait;

    private function next(User $user, string $when): array
    {
        $this->em()->clear();

        return static::getContainer()->get(NextStep::class)->forUser($this->em()->find(User::class, $user->getId()), new \DateTimeImmutable($when));
    }

    private ?\Symfony\Bundle\FrameworkBundle\KernelBrowser $client = null;

    /** Un compte qui a posé son But : les fondations ne sont plus la priorité. */
    private function withFoundations(): User
    {
        $this->client = static::createClient();
        $user = $this->createUser(pro: true);
        $this->client->loginUser($user);
        $this->client->request('POST', '/plan-de-vie/assistant?step=1&single=1', ['purpose_statement' => 'Je suis créé pour former afin d\'apporter la lumière chez les jeunes.']);

        return $user;
    }

    public function testNewUserStartsWithFoundationsWhateverTheHour(): void
    {
        static::createClient();
        $user = $this->createUser(pro: true);
        foreach (['2026-11-18 09:00', '2026-11-18 21:00', '2026-11-22 10:00'] as $when) {
            $this->assertSame('foundation', $this->next($user, $when)['kind'], $when);
        }
    }

    public function testEveningAsksForTheDailyReview(): void
    {
        $user = $this->withFoundations();
        $this->assertNotSame('evening', $this->next($user, '2026-11-18 10:00')['kind'], 'Pas le matin');
        $n = $this->next($user, '2026-11-18 19:00');
        $this->assertSame('evening', $n['kind']);
        $this->assertSame('app_daily_review', $n['route']);
        $this->assertCount(2, $n['then'], 'Deux actions « ensuite »');
    }

    public function testWeekendAsksForTheWeeklyReview(): void
    {
        $user = $this->withFoundations();
        $this->assertNotSame('weekly', $this->next($user, '2026-11-18 10:00')['kind'], 'Pas en semaine');
        $this->assertSame('weekly', $this->next($user, '2026-11-22 10:00')['kind'], 'Dimanche');
        // Samedi soir : le bilan du jour passe d'abord, la revue de la semaine vient juste après
        $sat = $this->next($user, '2026-11-21 19:00');
        $this->assertSame('evening', $sat['kind']);
        $this->assertContains('app_weekly_review', array_column($sat['then'], 'route'));
        $this->assertNotSame('weekly', $this->next($user, '2026-11-21 10:00')['kind'], 'Pas samedi matin');
    }

    public function testDuringTheDayItInvitesToTheJourneyThenToThePlans(): void
    {
        $user = $this->withFoundations();
        $n = $this->next($user, '2026-11-18 10:00');
        $this->assertSame('journey-start', $n['kind']);
        $this->assertSame('app_journey', $n['route']);

        $client = $this->client;
        $token = $client->request('GET', '/parcours')->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/parcours', ['_csrf_token' => $token, 'action' => 'start']);
        $n = $this->next($user, date('Y-m-d').' 10:00');
        $this->assertSame('journey', $n['kind']);
        $this->assertSame('semaine-1', $n['fragment']);

        // Semaine 1 terminée : on passe au plan le moins avancé
        $client->request('POST', '/parcours', ['_csrf_token' => $token, 'action' => 'toggle', 'week' => '1']);
        $n = $this->next($user, date('Y-m-d').' 10:00');
        $this->assertSame('plan', $n['kind']);
    }

    public function testDashboardShowsTheCardAndHidesTheDuplicate(): void
    {
        $client = static::createClient();
        $user = $this->createUser(pro: true);
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $client->request('GET', '/dashboard');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('[data-next-step="foundation"]', 'Ma prochaine étape');
        $this->assertSelectorTextContains('[data-next-step="foundation"]', 'Poser vos fondations');
        $this->assertSelectorNotExists('[data-journey-invite]', 'Plus de second encart : le parcours est proposé dans « Ma prochaine étape »');
    }
}
