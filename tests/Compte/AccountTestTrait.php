<?php

namespace App\Tests\Compte;

use App\Compte\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mime\Email;

/**
 * Outils partagés par les tests du module Compte.
 */
trait AccountTestTrait
{
    /** Utilisateurs créés pendant le test, supprimés à la fin. */
    private array $createdEmails = [];

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function uniqueEmail(string $prefix = 'compte'): string
    {
        return $this->createdEmails[] = $prefix.'-'.uniqid().'@exemple.org';
    }

    private function createUser(string $password = 'mot-de-passe-solide', bool $verified = true, bool $pro = false): User
    {
        $user = (new User())->setEmail($this->uniqueEmail())->setRoles([])->setIsVerified($verified);
        if ($pro) {
            $user->setSubscription((new \App\Abonnement\Entity\Subscription())
                ->setTier(\App\Abonnement\Enum\SubscriptionTier::PRO)->setStatus(\App\Abonnement\Enum\SubscriptionStatus::ACTIVE)
                ->setCurrentPeriodEnd(new \DateTime('+1 month')));
        }
        $user->setPassword(static::getContainer()->get('security.user_password_hasher')->hashPassword($user, $password));
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    private function findUser(string $email): ?User
    {
        $this->em()->clear();

        return $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
    }

    /** Dernier email envoyé (ou mis en file) pendant la requête précédente. */
    private function lastEmail(): Email
    {
        $messages = $this->getMailerMessages();
        $this->assertNotEmpty($messages, 'Un email aurait dû être envoyé');

        return end($messages);
    }

    private function codeFrom(Email $email): string
    {
        $this->assertMatchesRegularExpression('/\b(\d{6})\b/', $email->getSubject());
        preg_match('/\b(\d{6})\b/', $email->getSubject(), $m);

        return $m[1];
    }

    private function cleanUpUsers(): void
    {
        $conn = $this->em()->getConnection();
        foreach ($this->createdEmails as $email) {
            $conn->delete('`user`', ['email' => mb_strtolower($email)]);
        }
    }

    protected function tearDown(): void
    {
        $this->cleanUpUsers();
        parent::tearDown();
    }
}
