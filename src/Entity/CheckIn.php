<?php

namespace App\Entity;

use App\Repository\CheckInRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CheckInRepository::class)]
class CheckIn
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'checkIns')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $done = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $notDone = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $blocker = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adjustment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $action48h = null;

    public function __construct()
    {
        $this->date = new \DateTime();
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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getDone(): ?string
    {
        return $this->done;
    }

    public function setDone(string $done): static
    {
        $this->done = $done;

        return $this;
    }

    public function getNotDone(): ?string
    {
        return $this->notDone;
    }

    public function setNotDone(string $notDone): static
    {
        $this->notDone = $notDone;

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

    public function getAction48h(): ?string
    {
        return $this->action48h;
    }

    public function setAction48h(?string $action48h): static
    {
        $this->action48h = $action48h;

        return $this;
    }
}
