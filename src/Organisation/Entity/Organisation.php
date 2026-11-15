<?php

namespace App\Organisation\Entity;

use App\Compte\Entity\User;
use App\Organisation\Repository\OrganisationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Organisation cliente (église, association, entreprise, cabinet de coaching).
 * Elle achète un pack de places pour un an : chaque membre actif occupe une place et devient PRO.
 */
#[ORM\Entity(repositoryClass: OrganisationRepository::class)]
class Organisation
{
    public const TYPES = [
        'EGLISE' => 'Église',
        'ASSOCIATION' => 'Association',
        'ENTREPRISE' => 'Entreprise',
        'COACHING' => 'Coaching / formation',
        'AUTRE' => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $name;

    #[ORM\Column(length: 20)]
    private string $type = 'EGLISE';

    /** Nombre de places achetées (membres actifs maximum). */
    #[ORM\Column]
    private int $seats = 0;

    /** Fin de validité des places ; après cette date, les membres repassent en gratuit. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $seatsValidUntil = null;

    /** Code du lien d'invitation (/rejoindre/{code}) ; peut être régénéré. */
    #[ORM\Column(length: 32, unique: true)]
    private string $inviteCode;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Membership> */
    #[ORM\OneToMany(mappedBy: 'organisation', targetEntity: Membership::class, cascade: ['persist'])]
    private Collection $memberships;

    public function __construct(string $name, string $type, ?User $createdBy)
    {
        $this->name = $name;
        $this->type = isset(self::TYPES[$type]) ? $type : 'AUTRE';
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->regenerateInviteCode();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getType(): string { return $this->type; }
    public function getTypeLabel(): string { return self::TYPES[$this->type] ?? $this->type; }
    public function setType(string $type): static { $this->type = isset(self::TYPES[$type]) ? $type : 'AUTRE'; return $this; }
    public function getSeats(): int { return $this->seats; }
    public function setSeats(int $seats): static { $this->seats = max(0, $seats); return $this; }
    public function getSeatsValidUntil(): ?\DateTimeImmutable { return $this->seatsValidUntil; }
    public function setSeatsValidUntil(?\DateTimeImmutable $d): static { $this->seatsValidUntil = $d; return $this; }
    public function getInviteCode(): string { return $this->inviteCode; }
    public function regenerateInviteCode(): static { $this->inviteCode = bin2hex(random_bytes(8)); return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    /** @return Collection<int, Membership> */
    public function getMemberships(): Collection { return $this->memberships; }

    /** @return Membership[] membres actifs */
    public function getActiveMemberships(): array
    {
        return array_values(array_filter($this->memberships->toArray(), fn (Membership $m) => $m->isActive()));
    }

    public function isLicenseValid(): bool
    {
        return $this->seats > 0 && null !== $this->seatsValidUntil && $this->seatsValidUntil >= new \DateTimeImmutable('today');
    }

    public function getUsedSeats(): int
    {
        return \count($this->getActiveMemberships());
    }

    public function hasFreeSeat(): bool
    {
        return $this->getUsedSeats() < $this->seats;
    }
}
