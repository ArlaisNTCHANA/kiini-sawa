<?php

namespace App\Personnalite\Entity;

use App\Personnalite\Repository\PersonalityProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalityProfileRepository::class)]
#[ORM\Table(name: 'personality_profile')]
#[ORM\UniqueConstraint(name: 'UNIQ_TEST_TYPE_CODE', fields: ['testType', 'code'])]
#[ORM\HasLifecycleCallbacks]
class PersonalityProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $testType = null;

    #[ORM\Column(length: 50)]
    private ?string $code = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $strengths = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $weaknesses = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $relationships = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $career = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $emotionalDynamics = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $essence = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $growthAxes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $feedbackLow = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $feedbackMid = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $feedbackHigh = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
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

    public function getTestType(): ?string
    {
        return $this->testType;
    }

    public function setTestType(string $testType): static
    {
        $this->testType = $testType;

        return $this;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStrengths(): ?string
    {
        return $this->strengths;
    }

    public function setStrengths(?string $strengths): static
    {
        $this->strengths = $strengths;

        return $this;
    }

    public function getWeaknesses(): ?string
    {
        return $this->weaknesses;
    }

    public function setWeaknesses(?string $weaknesses): static
    {
        $this->weaknesses = $weaknesses;

        return $this;
    }

    public function getRelationships(): ?string
    {
        return $this->relationships;
    }

    public function setRelationships(?string $relationships): static
    {
        $this->relationships = $relationships;

        return $this;
    }

    public function getCareer(): ?string
    {
        return $this->career;
    }

    public function setCareer(?string $career): static
    {
        $this->career = $career;

        return $this;
    }

    public function getEmotionalDynamics(): ?string
    {
        return $this->emotionalDynamics;
    }

    public function setEmotionalDynamics(?string $emotionalDynamics): static
    {
        $this->emotionalDynamics = $emotionalDynamics;

        return $this;
    }

    public function getEssence(): ?string
    {
        return $this->essence;
    }

    public function setEssence(?string $essence): static
    {
        $this->essence = $essence;

        return $this;
    }

    public function getGrowthAxes(): ?string
    {
        return $this->growthAxes;
    }

    public function setGrowthAxes(?string $growthAxes): static
    {
        $this->growthAxes = $growthAxes;

        return $this;
    }

    public function getFeedbackLow(): ?string
    {
        return $this->feedbackLow;
    }

    public function setFeedbackLow(?string $feedbackLow): static
    {
        $this->feedbackLow = $feedbackLow;

        return $this;
    }

    public function getFeedbackMid(): ?string
    {
        return $this->feedbackMid;
    }

    public function setFeedbackMid(?string $feedbackMid): static
    {
        $this->feedbackMid = $feedbackMid;

        return $this;
    }

    public function getFeedbackHigh(): ?string
    {
        return $this->feedbackHigh;
    }

    public function setFeedbackHigh(?string $feedbackHigh): static
    {
        $this->feedbackHigh = $feedbackHigh;

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
