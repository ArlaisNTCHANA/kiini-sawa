<?php

namespace App\Notification\Entity;

use App\Compte\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Appareil (navigateur ou application installée) qui a accepté les notifications. */
#[ORM\Entity]
#[ORM\Index(name: 'idx_push_user', columns: ['user_id'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::TEXT)]
    private string $endpoint;

    /** Empreinte de l'adresse d'envoi (unique) : l'adresse elle-même est trop longue pour un index. */
    #[ORM\Column(length: 64, unique: true)]
    private string $endpointHash;

    #[ORM\Column(length: 255)]
    private string $p256dh;

    #[ORM\Column(length: 255)]
    private string $auth;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $endpoint, string $p256dh, string $auth, ?string $userAgent)
    {
        $this->user = $user;
        $this->endpoint = $endpoint;
        $this->endpointHash = hash('sha256', $endpoint);
        $this->p256dh = $p256dh;
        $this->auth = $auth;
        $this->userAgent = null !== $userAgent ? mb_substr($userAgent, 0, 255) : null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): static { $this->user = $user; return $this; }
    public function getEndpoint(): string { return $this->endpoint; }
    public function getP256dh(): string { return $this->p256dh; }
    public function getAuth(): string { return $this->auth; }
    public function setKeys(string $p256dh, string $auth): static { $this->p256dh = $p256dh; $this->auth = $auth; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
