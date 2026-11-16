<?php

namespace App\Tests\Media;

use App\Abonnement\Service\Entitlements;
use App\Compte\Repository\UserRepository;
use App\Media\Entity\Referral;
use App\Media\Service\ReferralService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Parrainage : lien personnel, filleul avec un mois d'essai, parrain récompensé à la vérification de l'email, une fois.
 */
class ReferralTest extends WebTestCase
{
    use AccountTestTrait;

    public function testCodesAreStableAndUnguessable(): void
    {
        static::createClient();
        $service = static::getContainer()->get(ReferralService::class);
        $u = $this->createUser();
        $code = $service->codeFor($u);
        $this->assertSame($code, $service->codeFor($u));
        $this->assertSame($u->getId(), $service->referrerFromCode($code)?->getId());
        $this->assertNull($service->referrerFromCode('ZZZZ'.substr($code, 4)), 'Signature falsifiée refusée');
        $this->assertNull($service->referrerFromCode('abc'));
    }

    public function testFullReferralJourney(): void
    {
        $client = static::createClient();
        $service = static::getContainer()->get(ReferralService::class);
        $referrer = $this->createUser(pro: true);
        $endBefore = static::getContainer()->get(Entitlements::class)->proUntil($referrer);

        $client->request('GET', '/i/'.$service->codeFor($referrer));
        $this->assertResponseRedirects();
        $this->assertNotNull($client->getCookieJar()->get(ReferralService::COOKIE));

        $email = $this->uniqueEmail('filleul');
        $form = $client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Timothée', 'email' => $email, 'password' => 'une phrase assez longue']);
        $referred = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
        $referral = $this->em()->getRepository(Referral::class)->findOneBy(['referred' => $referred]);
        $this->assertNotNull($referral);
        $this->assertNull($referral->getRewardedAt(), 'Pas de récompense avant la vérification');
        $trialDays = (int) $referred->getSubscription()->getTrialStartAt()->diff($referred->getSubscription()->getTrialEndAt())->days;
        $this->assertSame(ReferralService::REFERRED_TRIAL_DAYS, $trialDays, 'Un mois d\'essai pour le filleul');

        $code = $this->codeFrom($this->lastEmail());
        $client->followRedirect();
        $client->submitForm('Valider', ['code' => $code]);

        $this->em()->clear();
        $referral = $this->em()->getRepository(Referral::class)->find($referral->getId());
        $this->assertNotNull($referral->getRewardedAt());
        $referrer = static::getContainer()->get(UserRepository::class)->find($referrer->getId());
        $endAfter = static::getContainer()->get(Entitlements::class)->proUntil($referrer);
        $this->assertGreaterThanOrEqual(27, (int) $endBefore->diff($endAfter)->days, 'Un mois PRO ajouté au parrain');
        $this->assertFalse($service->reward($referral->getReferred()), 'Jamais deux fois');
    }

    public function testReferralPageShowsLinkAndResults(): void
    {
        $client = static::createClient();
        $user = $this->createUser();
        $user->setIsOnboardingCompleted(true);
        $this->em()->flush();
        $client->loginUser($user);
        $client->request('GET', '/parrainage');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('main', 'Inviter mes proches');
        $this->assertStringContainsString('/i/'.static::getContainer()->get(ReferralService::class)->codeFor($user), $client->getResponse()->getContent());
    }
}
