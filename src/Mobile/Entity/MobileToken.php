<?php

namespace App\Mobile\Entity;

use App\Compte\Entity\User;
use App\Mobile\Repository\MobileTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton de session mobile natif (accès court + rafraîchissement long à usage unique).
 * Seules les empreintes SHA-256 des jetons bruts sont conservées en base.
 */
#[ORM\Entity(repositoryClass: MobileTokenRepository::class)]
#[ORM\Table(name: 'mobile_token')]
#[ORM\Index(name: 'idx_mobile_token_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_mobile_token_access_hash', columns: ['access_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_mobile_token_refresh_hash', columns: ['refresh_hash'])]
class MobileToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'device_name', length: 120)]
    private string $deviceName;

    #[ORM\Column(name: 'access_hash', length: 64)]
    private string $accessHash;

    #[ORM\Column(name: 'refresh_hash', length: 64)]
    private string $refreshHash;

    #[ORM\Column(name: 'access_expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $accessExpiresAt;

    #[ORM\Column(name: 'refresh_expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $refreshExpiresAt;

    #[ORM\Column(name: 'last_used_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'revoked_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(name: 'rotated', options: ['default' => false])]
    private bool $rotated = false;

    public function __construct(
        User $user,
        string $deviceName,
        string $accessHash,
        string $refreshHash,
        \DateTimeImmutable $accessExpiresAt,
        \DateTimeImmutable $refreshExpiresAt,
        ?\DateTimeImmutable $now = null,
    ) {
        $now ??= new \DateTimeImmutable();
        $this->user = $user;
        $this->deviceName = mb_substr(trim($deviceName) ?: 'Appareil mobile', 0, 120);
        $this->accessHash = $accessHash;
        $this->refreshHash = $refreshHash;
        $this->accessExpiresAt = $accessExpiresAt;
        $this->refreshExpiresAt = $refreshExpiresAt;
        $this->createdAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    public function getAccessHash(): string
    {
        return $this->accessHash;
    }

    public function getRefreshHash(): string
    {
        return $this->refreshHash;
    }

    public function getAccessExpiresAt(): \DateTimeImmutable
    {
        return $this->accessExpiresAt;
    }

    public function getRefreshExpiresAt(): \DateTimeImmutable
    {
        return $this->refreshExpiresAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(?\DateTimeImmutable $at = null): static
    {
        $this->revokedAt ??= ($at ?? new \DateTimeImmutable());

        return $this;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isRotated(): bool
    {
        return $this->rotated;
    }

    public function markRotated(?\DateTimeImmutable $at = null): static
    {
        $this->rotated = true;

        return $this->revoke($at);
    }
}
