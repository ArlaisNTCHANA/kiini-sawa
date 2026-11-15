<?php

namespace App\Feedback\Entity;

use App\Feedback\Repository\CommunityFeedbackRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: CommunityFeedbackRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CommunityFeedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'communityFeedbacks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'communityFeedbacks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?FeedbackRequest $feedbackRequest = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $answers = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $relationship = null;

    #[ORM\Column]
    private bool $isAnonymous = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $respondentName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $strengths = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $improvements = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $qualities = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getFeedbackRequest(): ?FeedbackRequest
    {
        return $this->feedbackRequest;
    }

    public function setFeedbackRequest(?FeedbackRequest $feedbackRequest): static
    {
        $this->feedbackRequest = $feedbackRequest;

        return $this;
    }

    public function getAnswers(): ?string
    {
        return $this->answers;
    }

    public function setAnswers(?string $answers): static
    {
        $this->answers = $answers;

        return $this;
    }

    public function getRelationship(): ?string
    {
        return $this->relationship;
    }

    public function setRelationship(?string $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function isAnonymous(): bool
    {
        return $this->isAnonymous;
    }

    public function setIsAnonymous(bool $isAnonymous): static
    {
        $this->isAnonymous = $isAnonymous;

        return $this;
    }

    public function getRespondentName(): ?string
    {
        return $this->respondentName;
    }

    public function setRespondentName(?string $respondentName): static
    {
        $this->respondentName = $respondentName;

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

    public function getImprovements(): ?string
    {
        return $this->improvements;
    }

    public function setImprovements(?string $improvements): static
    {
        $this->improvements = $improvements;

        return $this;
    }

    public function getQualities(): ?string
    {
        return $this->qualities;
    }

    public function setQualities(?string $qualities): static
    {
        $this->qualities = $qualities;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
