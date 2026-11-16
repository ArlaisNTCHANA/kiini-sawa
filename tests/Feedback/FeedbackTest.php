<?php

namespace App\Tests\Feedback;

use App\Compte\Repository\UserRepository;
use App\Feedback\Entity\AppFeedback;
use App\Feedback\Service\FeedbackService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Feedback nourricier (regard des proches, anonymat protégé) et « Donner mon avis » sur l'application.
 */
class FeedbackTest extends WebTestCase
{
    use AccountTestTrait;

    public function testFriendsAnswerAndSummaryAppearsAfterThreeAnswers(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $profile = $owner->getUserProfile() ?? (new \App\Compte\Entity\UserProfile())->setUser($owner);
        $profile->setFirstName('Abigaïl');
        $this->em()->persist($profile);
        $owner->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($owner);

        $client->request('GET', '/mes-tests/feedback');
        $client->submitForm('Créer mon lien');
        $request = static::getContainer()->get(FeedbackService::class)->activeRequest($owner);
        $this->assertNotNull($request);
        $client->request('GET', '/logout');

        // Trois proches répondent (sessions différentes)
        foreach ([['Fiable', 'Patient'], ['Fiable', 'Sage'], ['Fiable', 'Courageux']] as $i => $qualities) {
            $client->getCookieJar()->clear();
            $crawler = $client->request('GET', '/avis/'.$request->getToken());
            $this->assertResponseIsSuccessful();
            $this->assertSelectorTextContains('h1', 'Abigaïl aimerait votre regard');
            $form = $crawler->selectButton('Envoyer mon regard')->form();
            foreach ($qualities as $q) {
                $form['qualities'][array_search($q, FeedbackService::QUALITIES, true)]->tick();
            }
            $client->submit($form, ['strengths' => 'Force numéro '.$i, 'improvements' => 2 === $i ? 'Oser dire non' : '']);
            $this->assertSelectorTextContains('main', 'Merci pour votre regard');
            if (1 === $i) {
                $summary = static::getContainer()->get(FeedbackService::class)->summary($request);
                $this->assertFalse($summary['detail'], 'Pas de détail avant 3 réponses');
            }
        }

        $client->loginUser(static::getContainer()->get(UserRepository::class)->find($owner->getId()));
        $client->request('GET', '/mes-tests/feedback');
        $this->assertSelectorTextContains('main', '3 réponses');
        $this->assertSelectorTextContains('main', 'Fiable · 3');
        $this->assertSelectorTextContains('main', 'Oser dire non');
    }

    public function testValidationAndDuplicatesAndClosedLinks(): void
    {
        $client = static::createClient();
        $owner = $this->createUser();
        $service = static::getContainer()->get(FeedbackService::class);
        $request = $service->open($owner, false);

        $this->assertNotEmpty($service->submit($request, ['qualities' => [], 'strengths' => '']));
        $this->assertNotEmpty($service->submit($request, ['qualities' => \array_slice(FeedbackService::QUALITIES, 0, 6), 'strengths' => 'x']), 'Au plus 5 qualités');

        $crawler = $client->request('GET', '/avis/'.$request->getToken());
        $form = $crawler->selectButton('Envoyer mon regard')->form();
        $form['qualities'][0]->tick();
        $client->submit($form, ['strengths' => 'Très à l\'écoute']);
        $client->request('GET', '/avis/'.$request->getToken());
        $this->assertSelectorTextContains('main', 'déjà répondu');

        $fresh = static::getContainer()->get(FeedbackService::class);
        $fresh->close($fresh->findOpen($request->getToken()));
        $client->request('GET', '/avis/'.$request->getToken());
        $this->assertResponseStatusCodeSame(404);
    }

    public function testAppFeedbackReachesTheAdmin(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $client->request('GET', '/donner-mon-avis?page=/journal');
        $message = 'Le bouton du journal pourrait être plus grand '.uniqid();
        $client->submitForm('Envoyer', ['category' => 'IDEE', 'content' => $message]);
        $this->assertResponseRedirects('/donner-mon-avis');
        $item = $this->em()->getRepository(AppFeedback::class)->findOneBy(['user' => $user]);
        $this->assertStringContainsString($message, $item->getContent());
        $this->assertStringContainsString('/journal', $item->getContent());

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/avis');
        $this->assertSelectorTextContains('body', $message);
    }
}
