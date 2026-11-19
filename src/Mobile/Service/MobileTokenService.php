<?php

namespace App\Mobile\Service;

use App\Compte\Entity\User;
use App\Mobile\Entity\MobileToken;
use App\Mobile\Repository\MobileTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Émission, rotation à usage unique et révocation des jetons mobiles.
 * Un jeton d'accès vit 20 minutes ; un jeton de rafraîchissement vit 60 jours.
 * La réutilisation d'un jeton de rafraîchissement déjà échangé révoque toute la session du compte.
 */
final class MobileTokenService
{
    public const ACCESS_TTL_SECONDS = 1200;
    public const REFRESH_TTL_DAYS = 60;
    public const MAX_ACTIVE_TOKENS = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MobileTokenRepository $tokens,
    ) {
    }

    /**
     * @return array{accessToken: string, refreshToken: string, expiresIn: int, token: MobileToken}
     */
    public function issue(User $user, ?string $deviceName = null, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $active = $this->tokens->findActiveForUser($user, $now);
        while (\count($active) >= self::MAX_ACTIVE_TOKENS) {
            $oldest = array_shift($active);
            if ($oldest instanceof MobileToken) {
                $oldest->revoke($now);
            }
        }

        $rawAccess = bin2hex(random_bytes(32));
        $rawRefresh = bin2hex(random_bytes(32));
        $accessHash = hash('sha256', $rawAccess);
        $refreshHash = hash('sha256', $rawRefresh);

        $cleanDevice = mb_substr(trim((string) ($deviceName ?? '')) ?: 'Appareil mobile', 0, 120);
        $token = new MobileToken(
            user: $user,
            deviceName: $cleanDevice,
            accessHash: $accessHash,
            refreshHash: $refreshHash,
            accessExpiresAt: $now->modify('+'.self::ACCESS_TTL_SECONDS.' seconds'),
            refreshExpiresAt: $now->modify('+'.self::REFRESH_TTL_DAYS.' days'),
            now: $now,
        );

        $this->em->persist($token);
        $this->em->flush();

        return [
            'accessToken' => $rawAccess,
            'refreshToken' => $rawRefresh,
            'expiresIn' => self::ACCESS_TTL_SECONDS,
            'token' => $token,
        ];
    }

    /**
     * @return array{accessToken: string, refreshToken: string, expiresIn: int, user: User}|null
     */
    public function rotate(string $rawRefresh, ?\DateTimeImmutable $now = null): ?array
    {
        $rawRefresh = trim($rawRefresh);
        if (\strlen($rawRefresh) < 32 || \strlen($rawRefresh) > 128) {
            return null;
        }

        $now ??= new \DateTimeImmutable();
        $token = $this->tokens->findByRefreshHash(hash('sha256', $rawRefresh));
        if (!$token instanceof MobileToken) {
            return null;
        }

        $user = $token->getUser();
        if ($token->isRevoked()) {
            if ($token->isRotated()) {
                // Réutilisation d'un jeton déjà échangé (vol probable) : on révoque toutes les sessions mobiles du compte
                $this->tokens->revokeAllForUser($user, $now);
            }

            return null;
        }

        if ($token->getRefreshExpiresAt() <= $now || !$user->isVerified() || \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $token->revoke($now);
            $this->em->flush();

            return null;
        }

        $deviceName = $token->getDeviceName();

        return $this->em->wrapInTransaction(function () use ($token, $user, $deviceName, $now): array {
            $token->markRotated($now);
            $issued = $this->issue($user, $deviceName, $now);

            return [
                'accessToken' => $issued['accessToken'],
                'refreshToken' => $issued['refreshToken'],
                'expiresIn' => $issued['expiresIn'],
                'user' => $user,
            ];
        });
    }

    public function findActiveByAccessToken(?string $rawAccess, ?\DateTimeImmutable $now = null): ?MobileToken
    {
        $rawAccess = trim((string) $rawAccess);
        if (\strlen($rawAccess) < 32 || \strlen($rawAccess) > 128) {
            return null;
        }

        return $this->tokens->findActiveByAccessHash(hash('sha256', $rawAccess), $now ?? new \DateTimeImmutable());
    }

    public function revokeByRawToken(?string $rawAccess, ?string $rawRefresh, ?\DateTimeImmutable $now = null): void
    {
        $now ??= new \DateTimeImmutable();
        $changed = false;

        foreach (['accessHash' => $rawAccess, 'refreshHash' => $rawRefresh] as $field => $raw) {
            $raw = trim((string) $raw);
            if (\strlen($raw) < 32 || \strlen($raw) > 128) {
                continue;
            }
            $token = $this->tokens->findOneBy([$field => hash('sha256', $raw)]);
            if ($token instanceof MobileToken && !$token->isRevoked()) {
                $token->revoke($now);
                $changed = true;
            }
        }

        if ($changed) {
            $this->em->flush();
        }
    }

    public function revokeAll(User $user, ?\DateTimeImmutable $now = null): void
    {
        $this->tokens->revokeAllForUser($user, $now ?? new \DateTimeImmutable());
    }
}
