<?php

namespace App\Tests\Notification;

use App\Notification\Service\OnboardingSequence;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Emails d'accueil : une étape par jour clé, adaptée à ce que l'utilisateur a déjà fait, une seule fois, désactivable.
 */
class OnboardingSequenceTest extends WebTestCase
{
    use AccountTestTrait;

    private function sequence(): OnboardingSequence
    {
        return static::getContainer()->get(OnboardingSequence::class);
    }

    private function day(int $n): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('today'))->modify('+'.$n.' days');
    }

    public function testWelcomeIsSentWhenEmailIsVerified(): void
    {
        $client = static::createClient();
        $form = $client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Lydie', 'email' => $this->uniqueEmail('accueil'), 'password' => 'une phrase assez longue', 'dateOfBirth' => '1990-05-12']);
        $code = $this->codeFrom($this->lastEmail());
        $client->followRedirect();
        $client->submitForm('Valider', ['code' => $code]);

        $email = $this->lastEmail();
        $this->assertSame('Bienvenue dans KIINI SAWA', $email->getSubject());
        $this->assertStringContainsString('Ne plus recevoir ces conseils', (string) $email->getHtmlBody());
        $this->assertTrue($email->getHeaders()->has('List-Unsubscribe'));
    }

    public function testStepsAdaptToWhatTheUserAlreadyDid(): void
    {
        static::createClient();
        $user = $this->createUser();
        static::getContainer()->get(\App\Abonnement\Service\Entitlements::class)->startTrial($user);
        $this->em()->flush();
        $seq = $this->sequence();

        $this->assertTrue($seq->sendFor($user, $this->day(1)), 'J1 : vision vide, email envoyé');
        $this->assertSame('Où voulez-vous être dans 10 ans ?', $this->lastEmail()->getSubject());
        $this->assertFalse($seq->sendFor($user, $this->day(1)), 'Jamais deux fois la même étape');
        $this->assertFalse($seq->sendFor($user, $this->day(2)), 'Pas d\'email les autres jours');

        $this->assertTrue($seq->sendFor($user, $this->day(3)));
        $this->assertSame('Un seul petit rituel pour commencer', $this->lastEmail()->getSubject());

        $this->assertTrue($seq->sendFor($user, $this->day(9)), 'J9 pendant l\'essai PRO');
        $this->assertStringContainsString('plan', $this->lastEmail()->getSubject());
    }

    public function testRitualAlreadyChosenLeadsToTheTestInstead(): void
    {
        static::createClient();
        $user = $this->createUser();
        $habit = (new Habit())->setTitle('Prière du matin')->setFrequency(HabitFrequency::DAILY);
        $habit->setUser($user);
        $this->em()->persist($habit);
        $this->em()->flush();

        $this->assertTrue($this->sequence()->sendFor($user, $this->day(3)));
        $this->assertSame('Découvrez comment Dieu vous a fait', $this->lastEmail()->getSubject());
        $this->assertFalse($this->sequence()->sendFor($user, $this->day(9)), 'Pas d\'email PRO sans essai en cours');
    }

    public function testUnsubscribedUsersReceiveNothing(): void
    {
        static::createClient();
        $user = $this->createUser();
        $notifier = static::getContainer()->get(\App\Notification\Service\Notifier::class);
        $notifier->preferences($user)->setNotifyOnboarding(false);
        $this->em()->flush();
        $this->assertFalse($this->sequence()->sendFor($user, $this->day(7)));
    }
}
