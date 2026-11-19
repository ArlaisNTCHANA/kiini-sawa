<?php

namespace App\Tests\Media;

use App\Compte\Repository\UserRepository;
use App\Media\Entity\SignupOrigin;
use App\Media\Service\OriginTracker;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Provenance des inscrits : lien suivi ou site d'origine retenus, recopiés à l'inscription, visibles dans l'admin.
 */
class SignupOriginTest extends WebTestCase
{
    use AccountTestTrait;

    private function signUp($client, string $email): void
    {
        $form = $client->request('GET', '/inscription')->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Ruth', 'email' => $email, 'password' => 'une phrase assez longue']);
    }

    private function originOf(string $email): ?SignupOrigin
    {
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);

        return $this->em()->getRepository(SignupOrigin::class)->findOneBy(['user' => $user]);
    }

    public function testTrackedLinkIsKeptUntilSignUp(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?utm_source=TT&utm_medium=video&utm_campaign=Defi21-Discipline&utm_content=video-12');
        $this->assertNotNull($client->getCookieJar()->get(OriginTracker::COOKIE), 'Cookie de provenance posé');

        // Navigation interne puis inscription : la provenance suit
        $client->request('GET', '/methode', [], [], ['HTTP_REFERER' => 'http://localhost/']);
        $email = $this->uniqueEmail('origine');
        $this->signUp($client, $email);

        $o = $this->originOf($email);
        $this->assertNotNull($o);
        $this->assertSame('tiktok', $o->getSource());
        $this->assertSame('video', $o->getMedium());
        $this->assertSame('defi21-discipline', $o->getCampaign());
        $this->assertSame('video-12', $o->getContent());
        $this->assertNull($client->getCookieJar()->get(OriginTracker::COOKIE), 'Cookie effacé après inscription');
    }

    public function testReferrerSiteAndDirectVisits(): void
    {
        $client = static::createClient();
        $client->request('GET', '/', [], [], ['HTTP_REFERER' => 'https://l.facebook.com/l.php?u=xyz']);
        $email = $this->uniqueEmail('facebook');
        $this->signUp($client, $email);
        $this->assertSame('facebook', $this->originOf($email)->getSource());
        $this->assertSame('l.facebook.com', $this->originOf($email)->getReferrer());

        $client->getCookieJar()->clear();
        $client->request('POST', '/logout', ['_csrf_token' => \App\Tests\Support\FixedCsrfTokenManager::VALUE]);
        $email2 = $this->uniqueEmail('direct');
        $this->signUp($client, $email2);
        $this->assertSame('direct', $this->originOf($email2)->getSource());
    }

    public function testAdminSeesFunnelByChannel(): void
    {
        $client = static::createClient();
        $client->request('GET', '/?utm_source=instagram&utm_medium=bio');
        $this->signUp($client, $this->uniqueEmail('insta'));

        $client->loginUser(static::getContainer()->get(UserRepository::class)->findOneByEmail('admin@kiinisawa.com'));
        $client->request('GET', '/admin/provenance?periode=7');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Instagram');
        $this->assertSelectorTextContains('body', 'Créer un lien suivi');
    }

    public function testPayingUsersAreCountedPerChannel(): void
    {
        static::createClient();
        $em = $this->em();
        $campaign = 'test-'.uniqid();
        $user = $this->createUser(pro: true);
        $em->persist((new SignupOrigin($user, 'youtube'))->setCampaign($campaign));
        $t = (new \App\Abonnement\Entity\Transaction())->setAmount(79.99)->setCurrency('EUR')->setStatus(\App\Abonnement\Entity\Transaction::STATUS_VALIDATED);
        $user->getSubscription()->addTransaction($t);
        $em->persist($t);
        $em->flush();

        $rows = array_column($em->getRepository(SignupOrigin::class)->funnel('campaign', new \DateTimeImmutable('-1 day')), null, 'label');
        $this->assertSame(1, $rows[$campaign]['signups']);
        $this->assertSame(1, $rows[$campaign]['paying']);
    }
}
