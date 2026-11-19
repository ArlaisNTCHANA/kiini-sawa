<?php

namespace App\Tests\Media;

use App\Compte\Entity\User;
use App\Compte\Repository\UserRepository;
use App\Media\Entity\Referral;
use App\Media\Service\ReferralService;
use App\Tests\Compte\AccountTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Audit de sécurité, lot 4 : parrainage, pas d'auto-parrainage par alias d'adresse, pas de récompenses en série depuis une même adresse IP. */
class ReferralSecurityTest extends WebTestCase
{
    use AccountTestTrait;

    #[\PHPUnit\Framework\Attributes\DataProvider('aliases')]
    public function testEmailAliasesOfTheSameMailboxAreRecognised(string $a, string $b): void
    {
        $this->assertSame(ReferralService::normalizeEmail($a), ReferralService::normalizeEmail($b), "$a et $b : même boîte");
    }

    /** @return iterable<string, array{string, string}> */
    public static function aliases(): iterable
    {
        yield 'étiquette +' => ['jean.dupont@example.com', 'jean.dupont+parrain@example.com'];
        yield 'majuscules' => ['Jean.Dupont@Example.COM', 'jean.dupont@example.com'];
        yield 'gmail : points' => ['jeandupont@gmail.com', 'j.e.a.n.d.u.p.o.n.t@gmail.com'];
        yield 'gmail : points et +' => ['jean.dupont@gmail.com', 'jeandupont+x@gmail.com'];
        yield 'googlemail' => ['jeandupont@googlemail.com', 'jean.dupont+y@gmail.com'];
        yield 'espaces' => ['  jean@example.com ', 'jean@example.com'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('differentMailboxes')]
    public function testDifferentMailboxesStayDifferent(string $a, string $b): void
    {
        $this->assertNotSame(ReferralService::normalizeEmail($a), ReferralService::normalizeEmail($b), "$a et $b : deux personnes");
    }

    /** @return iterable<string, array{string, string}> */
    public static function differentMailboxes(): iterable
    {
        yield 'autre domaine' => ['jean@example.com', 'jean@example.org'];
        yield 'points hors gmail' => ['jean.dupont@example.com', 'jeandupont@example.com'];
        yield 'autre nom' => ['jean+a@gmail.com', 'marie+a@gmail.com'];
    }

    private function signUp(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, User $referrer, string $email, string $ip = '203.0.113.7'): ?User
    {
        $service = static::getContainer()->get(ReferralService::class);
        $client->request('GET', '/i/'.$service->codeFor($referrer), server: ['REMOTE_ADDR' => $ip]);
        $form = $client->request('GET', '/inscription', server: ['REMOTE_ADDR' => $ip])->selectButton('Créer mon compte')->form();
        $form['acceptTerms']->tick();
        $client->submit($form, ['firstName' => 'Filleul', 'email' => $email, 'password' => 'une phrase assez longue'], ['REMOTE_ADDR' => $ip]);

        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }

    public function testSelfReferralByAliasIsRefused(): void
    {
        $client = static::createClient();
        $local = 'parrain'.bin2hex(random_bytes(3));
        $referrer = $this->createUser();
        $referrer->setEmail($local.'@gmail.com');
        $this->em()->flush();

        $self = $this->signUp($client, $referrer, mb_substr($local, 0, 3).'.'.mb_substr($local, 3).'+moi@gmail.com');
        $this->assertNotNull($self, 'le compte est créé (l\'inscription reste ouverte)');
        $this->assertNull($this->em()->getRepository(Referral::class)->findOneBy(['referred' => $self->getId()]), 'mais aucun parrainage n\'est enregistré');
    }

    public function testRewardsFromTheSameIpAreLimitedPerReferrer(): void
    {
        $client = static::createClient();
        $referrer = $this->createUser();
        $first = $this->signUp($client, $referrer, $this->uniqueEmail('filleul-a'), '198.51.100.20');
        $client->restart();
        $second = $this->signUp($client, $referrer, $this->uniqueEmail('filleul-b'), '198.51.100.20');
        $client->restart();
        $other = $this->signUp($client, $referrer, $this->uniqueEmail('filleul-c'), '198.51.100.99');

        $service = static::getContainer()->get(ReferralService::class);
        $em = $this->em();
        $this->assertNotNull($em->getRepository(Referral::class)->findOneBy(['referred' => $first->getId()])->getSignupIpHash());
        $this->assertNotSame('198.51.100.20', $em->getRepository(Referral::class)->findOneBy(['referred' => $first->getId()])->getSignupIpHash(), 'jamais l\'adresse en clair');

        $this->assertTrue($service->reward($em->find(User::class, $first->getId())), 'premier filleul de cette adresse : récompensé');
        $this->assertFalse($service->reward($em->find(User::class, $second->getId())), 'deuxième depuis la même adresse : pas de récompense');
        $this->assertTrue($service->reward($em->find(User::class, $other->getId())), 'une autre adresse reste récompensée');
        $this->assertSame(1, ReferralService::MAX_REWARDS_PER_IP);
    }
}
