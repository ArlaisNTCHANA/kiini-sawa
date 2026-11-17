<?php

namespace App\PlanDIntendance\Entity;

use App\Compte\Entity\User;
use App\PlanDIntendance\Repository\StewardshipItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * « Ce qui m'est confié » : un élément de l'inventaire du Plan d'Intendance (livre, chapitre 18).
 *
 * Cycle : inventaire (origine + libellé) → état (fructifie, dort, fuit) → décision (affecter, protéger, libérer)
 * avec une action des 30 prochains jours → compte rendu (action faite, date du dernier point).
 */
#[ORM\Entity(repositoryClass: StewardshipItemRepository::class)]
#[ORM\Index(name: 'idx_stewardship_user', columns: ['user_id', 'position'])]
class StewardshipItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Un des 7 domaines ou une sortie du Plan de Vie : voir Stewardship::ORIGINS. */
    #[ORM\Column(length: 30)]
    private string $origin = 'FINANCES';

    #[ORM\Column(length: 255)]
    private string $label = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    /** FRUCTIFIE, DORT, FUIT ou null (pas encore évalué). */
    #[ORM\Column(length: 12, nullable: true)]
    private ?string $status = null;

    /** AFFECTER, PROTEGER, LIBERER ou null. */
    #[ORM\Column(length: 12, nullable: true)]
    private ?string $decision = null;

    /** Action des 30 prochains jours. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionText = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $priority = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $actionDoneAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getOrigin(): string { return $this->origin; }

    public function setOrigin(string $origin): static { $this->origin = $origin; return $this->touch(); }

    public function getLabel(): string { return $this->label; }

    public function setLabel(string $label): static { $this->label = mb_substr(trim($label), 0, 255); return $this->touch(); }

    public function getNote(): ?string { return $this->note; }

    public function setNote(?string $note): static { $this->note = '' === trim((string) $note) ? null : trim((string) $note); return $this->touch(); }

    public function getStatus(): ?string { return $this->status; }

    public function setStatus(?string $status): static { $this->status = $status ?: null; return $this->touch(); }

    public function getDecision(): ?string { return $this->decision; }

    public function setDecision(?string $decision): static { $this->decision = $decision ?: null; return $this->touch(); }

    public function getActionText(): ?string { return $this->actionText; }

    public function setActionText(?string $actionText): static
    {
        $text = '' === trim((string) $actionText) ? null : mb_substr(trim((string) $actionText), 0, 255);
        if ($text !== $this->actionText) {
            $this->actionDoneAt = null; // nouvelle action : pas encore faite
        }
        $this->actionText = $text;

        return $this->touch();
    }

    public function isPriority(): bool { return $this->priority; }

    public function setPriority(bool $priority): static { $this->priority = $priority; return $this->touch(); }

    public function getActionDoneAt(): ?\DateTimeImmutable { return $this->actionDoneAt; }

    public function setActionDoneAt(?\DateTimeImmutable $at): static { $this->actionDoneAt = $at; return $this->touch(); }

    public function getPosition(): int { return $this->position; }

    public function setPosition(int $position): static { $this->position = $position; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    private function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
