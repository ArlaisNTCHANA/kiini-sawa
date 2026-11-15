<?php

namespace App\Entity;

use App\Enum\ReflectionType;
use App\Repository\ReflectionNoteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReflectionNoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ReflectionNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reflectionNotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'notes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?JournalCollection $collection = null;

    #[ORM\ManyToOne(inversedBy: 'reflectionNotes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LifeGoal $goal = null;

    #[ORM\ManyToOne(inversedBy: 'reflectionNotes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GrowthGoal $growthGoal = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mood = null;

    #[ORM\Column(nullable: true)]
    private ?int $energy = null;

    #[ORM\Column(length: 50, type: 'string', enumType: ReflectionType::class)]
    private ReflectionType $type = ReflectionType::REFLECTION;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->date = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getCollection(): ?JournalCollection
    {
        return $this->collection;
    }

    public function setCollection(?JournalCollection $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function getGoal(): ?LifeGoal
    {
        return $this->goal;
    }

    public function setGoal(?LifeGoal $goal): static
    {
        $this->goal = $goal;

        return $this;
    }

    public function getGrowthGoal(): ?GrowthGoal
    {
        return $this->growthGoal;
    }

    public function setGrowthGoal(?GrowthGoal $growthGoal): static
    {
        $this->growthGoal = $growthGoal;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

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

    public function getMood(): ?string
    {
        return $this->mood;
    }

    public function setMood(?string $mood): static
    {
        $this->mood = $mood;

        return $this;
    }

    public function getEnergy(): ?int
    {
        return $this->energy;
    }

    public function setEnergy(?int $energy): static
    {
        $this->energy = $energy;

        return $this;
    }

    public function getType(): ReflectionType
    {
        return $this->type;
    }

    public function setType(ReflectionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }
}
