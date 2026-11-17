<?php

namespace App\Personnalite\Entity;

use App\Compte\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande de confirmation d'un résultat de test (DISC, dons spirituels) envoyée aux proches par un lien personnel.
 */
#[ORM\Entity]
#[ORM\Table(name: 'confirmation_request')]
#[ORM\Index(name: 'idx_confirmation_user', columns: ['user_id', 'instrument'])]
class ConfirmationRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 30)]
    private string $instrument;

    #[ORM\Column(length: 24, unique: true)]
    private string $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    /** @var Collection<int, ConfirmationAnswer> */
    #[ORM\OneToMany(targetEntity: ConfirmationAnswer::class, mappedBy: 'request', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $answers;

    public function __construct(User $user, string $instrument)
    {
        $this->user = $user;
        $this->instrument = $instrument;
        $this->token = bin2hex(random_bytes(12));
        $this->createdAt = new \DateTimeImmutable();
        $this->answers = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getInstrument(): string { return $this->instrument; }
    public function getToken(): string { return $this->token; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function isOpen(): bool { return null === $this->closedAt; }
    public function close(): void { $this->closedAt = new \DateTimeImmutable(); }
    public function reopen(): void { $this->closedAt = null; }

    /** @return Collection<int, ConfirmationAnswer> */
    public function getAnswers(): Collection { return $this->answers; }
}
