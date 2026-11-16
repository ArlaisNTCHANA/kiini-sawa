<?php

namespace App\Tests\Defis;

use App\Compte\Repository\UserRepository;
use App\Defis\Entity\Challenge;
use App\Defis\Service\ChallengeService;
use App\Notification\Service\ReminderService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Défis collectifs : création par l'admin, page publique, inscription (même sans compte), validation quotidienne,
 * réussite, classement, email de l'action du jour.
 */
class ChallengeTest extends WebTestCase
{
    use AccountTestTrait;

    private function makeChallenge(string $start = 'today', int $days = 5, bool $published = true): Challenge
    {
        $admin = static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com');
        $s = new \DateTime($start.' midnight');
        $c = (new Challenge())->setCreator($admin)->setTitle('Défi test '.uniqid())->setSlug('defi-test-'.uniqid())
            ->setHabitTitle('Prier 10 minutes')->setDailyActions(['Écrire pourquoi je relève ce défi'])
            ->setStartDate($s)->setEndDate((clone $s)->modify('+'.($days - 1).' days'))->setPublished($published);
        $this->em()->persist($c);
        $this->em()->flush();

        return $c;
    }

    public function testAdminCreatesAndPublishesAChallenge(): void
    {
        $client = static::createClient();
        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/defis/nouveau');
        $this->assertResponseIsSuccessful();
        $title = '21 jours de discipline '.uniqid();
        $client->submitForm('Enregistrer', ['title' => $title, 'startDate' => (new \DateTime('+3 days'))->format('Y-m-d'), 'duration' => 21, 'habitTitle' => 'Me lever à l\'heure prévue', 'published' => '1']);
        $this->assertResponseRedirects();
        $c = $this->em()->getRepository(Challenge::class)->findOneBy(['title' => $title]);
        $this->assertNotNull($c);
        $this->assertSame(21, $c->getDurationDays());
        $this->assertMatchesRegularExpression('/^21-jours-de-discipline/', $c->getSlug());

        $client->request('GET', '/defi/'.$c->getSlug());
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Commence le');
    }

    public function testVisitorJoinsThroughSignUpThenChecksIn(): void
    {
        $client = static::createClient();
        $c = $this->makeChallenge();
        $client->request('GET', '/defi/'.$c->getSlug());
        $this->assertResponseIsSuccessful();
        $client->submitForm('Je relève le défi');
        $this->assertResponseRedirects('/inscription');

        $form = $client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Marthe', 'email' => $this->uniqueEmail('defi'), 'password' => 'une phrase assez longue']);
        $code = $this->codeFrom($this->lastEmail());
        $client->followRedirect();
        $client->submitForm('Valider', ['code' => $code]);
        $this->assertResponseRedirects('/defi/'.$c->getSlug(), null, 'Retour automatique sur le défi');
        $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Votre action du jour 1');
        $this->assertSelectorTextContains('main', 'Écrire pourquoi je relève ce défi');

        $client->submitForm('J\'ai fait ma part aujourd\'hui ✓');
        $client->followRedirect();
        $this->assertSelectorTextContains('main', 'Journée validée');
        $this->assertSelectorTextContains('main', 'Marthe');
        // Une seule validation par jour
        $p = static::getContainer()->get(ChallengeService::class)->participation($this->em()->find(Challenge::class, $c->getId()), static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $client->getRequest()->getSession()->get('_security.last_username') ?? '']) ?? $this->em()->getRepository(\App\Defis\Entity\ChallengeParticipant::class)->findOneBy(['challenge' => $c])->getUser());
        $this->assertSame(1, $p->getProgress());
        $this->assertFalse(static::getContainer()->get(ChallengeService::class)->checkIn($p, new \DateTimeImmutable('today')));
    }

    public function testRulesSuccessAndDailyEmail(): void
    {
        static::createClient();
        $service = static::getContainer()->get(ChallengeService::class);
        $c = $this->makeChallenge('-4 days', 5);
        $user = $this->createUser();
        $p = $service->join($c, $user);
        $today = new \DateTimeImmutable('today');
        $this->assertSame(5, $service->dayNumber($c, $today));
        $this->assertSame(4, $service->successThreshold($c));

        foreach ([-3, -2, -1] as $offset) {
            $this->assertTrue($service->checkIn($p, $today->modify($offset.' days')));
        }
        $this->assertFalse($service->isSuccessful($p));
        $this->assertTrue($service->checkIn($p, $today));
        $this->assertTrue($service->isSuccessful($p), '4 jours sur 5 : défi relevé');
        $this->assertSame(4, $service->streak($p, $today));
        $this->assertFalse($service->checkIn($p, $today->modify('+1 day')), 'Pas de validation après la fin');

        $this->assertSame(1, static::getContainer()->get(ReminderService::class)->challengeDay($user, $today));
        $this->assertStringContainsString('jour 5', $this->lastEmail()->getSubject());
        $this->assertSame(0, static::getContainer()->get(ReminderService::class)->challengeDay($user, $today), 'Une fois par jour');
    }

    public function testDraftsAreHidden(): void
    {
        $client = static::createClient();
        $c = $this->makeChallenge('today', 5, false);
        $client->request('GET', '/defi/'.$c->getSlug());
        $this->assertResponseStatusCodeSame(404);
    }
}
