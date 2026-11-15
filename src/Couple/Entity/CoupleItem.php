<?php

namespace App\Couple\Entity;

use App\Compte\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Élément commun du couple : valeur, projet, thème de croissance ou rituel.
 * Les deux conjoints peuvent le créer, le modifier et le supprimer.
 */
#[ORM\Entity]
class CoupleItem
{
    public const KINDS = ['VALUE' => 'Valeur', 'PROJECT' => 'Projet', 'THEME' => 'Thème de croissance', 'RITUAL' => 'Rituel'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Couple $couple;

    #[ORM\Column(length: 10)]
    private string $kind;

    #[ORM\Column(length: 255)]
    private string $title;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    /** Projet : BE/DO/HAVE/GIVE ; thème : clé du catalogue ; rituel : fréquence. */
    #[ORM\Column(length: 30, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column]
    private int $progress = 0;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $createdBy;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Couple $couple, string $kind, string $title, ?User $createdBy)
    {
        $this->couple = $couple;
        $this->kind = $kind;
        $this->title = $title;
        $this->createdBy = $createdBy;
        $this->createdAt = new \DateTimeImmutable();
        $couple->getItems()->add($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getCouple(): Couple { return $this->couple; }
    public function getKind(): string { return $this->kind; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $details): static { $this->details = $details; return $this; }
    public function getCategory(): ?string { return $this->category; }
    public function setCategory(?string $category): static { $this->category = $category; return $this; }
    public function getDeadline(): ?\DateTimeImmutable { return $this->deadline; }
    public function setDeadline(?\DateTimeImmutable $deadline): static { $this->deadline = $deadline; return $this; }
    public function getProgress(): int { return $this->progress; }
    public function setProgress(int $progress): static { $this->progress = max(0, min(100, $progress)); return $this; }
    public function getCreatedBy(): ?User { return $this->createdBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
