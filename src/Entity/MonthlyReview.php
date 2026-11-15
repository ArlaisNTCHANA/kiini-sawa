<?php

namespace App\Entity;

use App\Repository\MonthlyReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MonthlyReviewRepository::class)]
#[ORM\HasLifecycleCallbacks]
class MonthlyReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'monthlyReviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $month = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $progress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $stagnation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $blocker = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adjustment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $decision = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->month = new \DateTime();
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getMonth(): ?\DateTimeInterface
    {
        return $this->month;
    }

    public function setMonth(\DateTimeInterface $month): static
    {
        $this->month = $month;

        return $this;
    }

    public function getProgress(): ?string
    {
        return $this->progress;
    }

    public function setProgress(?string $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function getStagnation(): ?string
    {
        return $this->stagnation;
    }

    public function setStagnation(?string $stagnation): static
    {
        $this->stagnation = $stagnation;

        return $this;
    }

    public function getBlocker(): ?string
    {
        return $this->blocker;
    }

    public function setBlocker(?string $blocker): static
    {
        $this->blocker = $blocker;

        return $this;
    }

    public function getAdjustment(): ?string
    {
        return $this->adjustment;
    }

    public function setAdjustment(?string $adjustment): static
    {
        $this->adjustment = $adjustment;

        return $this;
    }

    public function getDecision(): ?string
    {
        return $this->decision;
    }

    public function setDecision(?string $decision): static
    {
        $this->decision = $decision;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
