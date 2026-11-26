<?php

namespace App\Compte\Service;

use App\Compte\Entity\AccountToken;
use App\Compte\Entity\User;
use App\Compte\Repository\AccountTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Crée et vérifie les jetons de compte.
 *
 * - Codes à 6 chiffres (vérification et changement d'e-mail) : 15 minutes, 5 essais.
 * - Liens de réinitialisation du mot de passe : sélecteur public + secret, 1 heure.
 *
 * Seule l'empreinte HMAC du secret est stockée, et la comparaison se fait
 * en temps constant.
 */
final class AccountTokenService
{
    public const CODE_TTL = '+15 minutes';
    public const RESET_TTL = '+1 hour';
    public const MAX_ATTEMPTS = 5;
    /** Délai minimal entre deux envois de code. */
    public const RESEND_DELAY_SECONDS = 60;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountTokenRepository $tokens,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
    }

    /** Crée un code à 6 chiffres (remplace les codes précédents du même type) et le renvoie en clair. */
    public function createCode(User $user, string $purpose, ?string $payload = null): string
    {
        $this->tokens->removeAll($user, $purpose);
        $code = str_pad((string) random_int(0, 999999), 6, '0', \STR_PAD_LEFT);
        $this->em->persist(new AccountToken($user, $purpose, $this->hash($code), new \DateTimeImmutable(self::CODE_TTL), null, $payload));
        $this->em->flush();

        return $code;
    }

    /** Un nouveau code peut-il être envoyé (anti-spam) ? */
    public function canResend(User $user, string $purpose): bool
    {
        $last = $this->tokens->findLatest($user, $purpose);

        return null === $last || $last->getCreatedAt() <= new \DateTimeImmutable('-'.self::RESEND_DELAY_SECONDS.' seconds');
    }

    /**
     * Vérifie un code. Renvoie le jeton s'il est valide (et le supprime),
     * sinon null. Chaque échec compte comme un essai.
     */
    public function consumeCode(User $user, string $purpose, string $code): ?AccountToken
    {
        $token = $this->tokens->findLatest($user, $purpose);
        if (null === $token || $token->isExpired() || $token->getAttempts() >= self::MAX_ATTEMPTS) {
            return null;
        }

        $code = preg_replace('/\D/', '', $code);
        if (!hash_equals($token->getSecretHash(), $this->hash($code))) {
            $token->incrementAttempts();
            $this->em->flush();

            return null;
        }

        $this->em->remove($token);
        $this->em->flush();

        return $token;
    }

    /** Crée un lien de réinitialisation ; renvoie [selector, secret]. */
    public function createResetToken(User $user): array
    {
        $this->tokens->removeAll($user, AccountToken::RESET_PASSWORD);
        $selector = bin2hex(random_bytes(12));
        $secret = bin2hex(random_bytes(20));
        $this->em->persist(new AccountToken($user, AccountToken::RESET_PASSWORD, $this->hash($selector.$secret), new \DateTimeImmutable(self::RESET_TTL), $selector));
        $this->em->flush();

        return [$selector, $secret];
    }

    /** Retrouve le jeton de réinitialisation valide, sans le consommer. */
    public function findResetToken(string $selector, string $secret): ?AccountToken
    {
        $token = $this->tokens->findOneBy(['selector' => $selector, 'purpose' => AccountToken::RESET_PASSWORD]);
        if (null === $token || $token->isExpired() || !hash_equals($token->getSecretHash(), $this->hash($selector.$secret))) {
            return null;
        }

        return $token;
    }

    public function removeAll(User $user, string $purpose): void
    {
        $this->tokens->removeAll($user, $purpose);
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret);
    }
}
