<?php

namespace App\Social\Entity;

use App\Social\Repository\CommentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;
use App\PlanDHygiene\Entity\HygieneDomain;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeDeveloppement\Entity\SkillGoal;
use App\PlanDeTransmission\Entity\LegacyGoal;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\Purpose;
use App\PlanDeVie\Entity\Vision;
use App\Rituels\Entity\Habit;

#[ORM\Entity(repositoryClass: CommentRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Comment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $author = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Purpose $purpose = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Vision $vision = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?LifeGoal $lifeGoal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Habit $habit = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?GrowthGoal $growthGoal = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?SkillGoal $skillGoal = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?HygieneDomain $hygieneDomain = null;

    #[ORM\ManyToOne(inversedBy: 'comments')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?LegacyGoal $legacyGoal = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $targetUser = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $section = null;

    public function __construct()
    {
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

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getPurpose(): ?Purpose
    {
        return $this->purpose;
    }

    public function setPurpose(?Purpose $purpose): static
    {
        $this->purpose = $purpose;

        return $this;
    }

    public function getVision(): ?Vision
    {
        return $this->vision;
    }

    public function setVision(?Vision $vision): static
    {
        $this->vision = $vision;

        return $this;
    }

    public function getLifeGoal(): ?LifeGoal
    {
        return $this->lifeGoal;
    }

    public function setLifeGoal(?LifeGoal $lifeGoal): static
    {
        $this->lifeGoal = $lifeGoal;

        return $this;
    }

    public function getHabit(): ?Habit
    {
        return $this->habit;
    }

    public function setHabit(?Habit $habit): static
    {
        $this->habit = $habit;

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

    public function getSkillGoal(): ?SkillGoal
    {
        return $this->skillGoal;
    }

    public function setSkillGoal(?SkillGoal $skillGoal): static
    {
        $this->skillGoal = $skillGoal;

        return $this;
    }

    public function getHygieneDomain(): ?HygieneDomain
    {
        return $this->hygieneDomain;
    }

    public function setHygieneDomain(?HygieneDomain $hygieneDomain): static
    {
        $this->hygieneDomain = $hygieneDomain;

        return $this;
    }

    public function getLegacyGoal(): ?LegacyGoal
    {
        return $this->legacyGoal;
    }

    public function setLegacyGoal(?LegacyGoal $legacyGoal): static
    {
        $this->legacyGoal = $legacyGoal;

        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }

    public function setTargetUser(?User $targetUser): static
    {
        $this->targetUser = $targetUser;

        return $this;
    }

    public function getSection(): ?string
    {
        return $this->section;
    }

    public function setSection(?string $section): static
    {
        $this->section = $section;

        return $this;
    }
}
