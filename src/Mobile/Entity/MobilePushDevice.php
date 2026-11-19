<?php

namespace App\Mobile\Entity;

use App\Compte\Entity\User;
use App\Mobile\Repository\MobilePushDeviceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Appareil mobile enregistré pour les notifications Expo Push.
 * Chaque appareil est identifié par le hash SHA-256 de son jeton Expo.
 */
#[ORM\Entity(repositoryClass: MobilePushDeviceRepository::class)]
#[ORM\Table(name: 'mobile_push_device')]
#[ORM\Index(name: 'idx_mobile_push_user', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_mobile_push_token_hash', columns: ['token_hash'])]
class MobilePushDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'token_hash', length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'expo_token', length: 255)]
    private string $expoToken;

    #[ORM\Column(name: 'platform', length: 20)]
    private string $platform;

    #[ORM\Column(name: 'device_name', length: 120, nullable: true)]
    private ?string $deviceName = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_seen_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastSeenAt;

    public function __construct(
        User $user,
        string $expoToken,
        string $platform = 'unknown',
        ?string $deviceName = null,
        ?\DateTimeImmutable $now = null,
    ) {
        $now ??= new \DateTimeImmutable();
        $this->user = $user;
        $this->expoToken = trim($expoToken);
        $this->tokenHash = hash('sha256', $this->expoToken);
        $this->platform = in_array($platform, ['ios', 'android', 'unknown'], true) ? $platform : 'unknown';
        $this->deviceName = null !== $deviceName && '' !== trim($deviceName) ? mb_substr(trim($deviceName), 0, 120) : null;
        $this->createdAt = $now;
        $this->lastSeenAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpoToken(): string
    {
        return $this->expoToken;
    }

    public function setExpoToken(string $expoToken): static
    {
        $this->expoToken = trim($expoToken);
        $this->tokenHash = hash('sha256', $this->expoToken);

        return $this;
    }

    public function getPlatform(): string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): static
    {
        $this->platform = in_array($platform, ['ios', 'android', 'unknown'], true) ? $platform : 'unknown';

        return $this;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): static
    {
        $this->deviceName = null !== $deviceName && '' !== trim($deviceName) ? mb_substr(trim($deviceName), 0, 120) : null;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function touch(?\DateTimeImmutable $at = null): static
    {
        $this->lastSeenAt = $at ?? new \DateTimeImmutable();

        return $this;
    }
}
