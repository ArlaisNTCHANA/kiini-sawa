<?php

namespace App\Compte\Entity;

use App\Compte\Repository\AccountTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Jeton à usage unique lié à un compte : code de vérification d'email,
 * changement d'email ou réinitialisation du mot de passe.
 *
 * Le secret (code à 6 chiffres ou partie secrète du lien) n'est jamais
 * stocké en clair : seule son empreinte HMAC l'est (voir AccountTokenService).
 */
#[ORM\Entity(repositoryClass: AccountTokenRepository::class)]
#[ORM\Index(name: 'idx_account_token_selector', columns: ['selector'])]
class AccountToken
{
    public const VERIFY_EMAIL = 'VERIFY_EMAIL';
    public const CHANGE_EMAIL = 'CHANGE_EMAIL';
    public const RESET_PASSWORD = 'RESET_PASSWORD';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20)]
    private string $purpose;

    /** Partie publique du lien (réinitialisation) ; null pour les codes à 6 chiffres. */
    #[ORM\Column(length: 32, nullable: true)]
    private ?string $selector = null;

    #[ORM\Column(length: 64)]
    private string $secretHash;

    /** Nouvel email en attente de confirmation (CHANGE_EMAIL). */
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $payload = null;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $purpose, string $secretHash, \DateTimeImmutable $expiresAt, ?string $selector = null, ?string $payload = null)
    {
        $this->user = $user;
        $this->purpose = $purpose;
        $this->secretHash = $secretHash;
        $this->expiresAt = $expiresAt;
        $this->selector = $selector;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPurpose(): string
    {
        return $this->purpose;
    }

    public function getSelector(): ?string
    {
        return $this->selector;
    }

    public function getSecretHash(): string
    {
        return $this->secretHash;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        ++$this->attempts;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }
}
