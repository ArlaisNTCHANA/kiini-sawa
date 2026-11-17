<?php

namespace App\Tests\Methode;

use App\Methode\Entity\JourneyProgress;
use App\Methode\Service\BookLibrary;
use App\Methode\Service\Journey;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parcours de 90 jours (livre, annexe A).
 */
class JourneyTest extends WebTestCase
{
    use AccountTestTrait;

    public function testEveryWeekPointsToRealChaptersAndScreens(): void
    {
        static::bootKernel();
        $router = static::getContainer()->get('router');
        $this->assertCount(13, Journey::PLAN);
        foreach (Journey::PLAN as $n => $week) {
            foreach ($week['chapters'] as $slug) {
                $this->assertContains($slug, BookLibrary::CHAPTERS, "Semaine $n : chapitre $slug");
            }
            foreach ($week['actions'] as $action) {
                $this->assertNotEmpty($router->generate($action[1], $action[2] ?? []), "Semaine $n : écran $action[1]");
            }
        }
        $this->assertSame(1, Journey::currentWeek(new \DateTimeImmutable('today')));
        $this->assertSame(3, Journey::currentWeek(new \DateTimeImmutable('-15 days')));
        $this->assertSame(13, Journey::currentWeek(new \DateTimeImmutable('-200 days')));
    }

    public function testStartAndCompleteWeeks(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);

        $client->request('GET', '/dashboard');
        $this->assertSelectorExists('[data-journey-invite]');

        $crawler = $client->request('GET', '/parcours');
        $this->assertResponseIsSuccessful();
        $this->assertCount(13, $crawler->filter('[data-week]'));
        $this->assertSelectorTextContains('[data-week="11"]', 'ton inventaire d\'intendance');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/parcours', ['_csrf_token' => $token, 'action' => 'start']);
        $client->request('POST', '/parcours', ['_csrf_token' => $token, 'action' => 'toggle', 'week' => '1']);
        $this->em()->clear();
        $progress = $this->em()->getRepository(JourneyProgress::class)->findOneBy(['user' => $user->getId()]);
        $this->assertSame([1], $progress->getCompletedWeeks());

        $client->request('GET', '/parcours');
        $this->assertSelectorTextContains('[data-journey-status]', 'Semaine 1');
        $client->request('GET', '/dashboard');
        $this->assertSelectorTextContains('[data-journey-reminder]', 'semaine 1 sur 13');
    }
}
