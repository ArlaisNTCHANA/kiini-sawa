<?php

namespace App\Feedback\Entity;

use App\Feedback\Repository\FeedbackRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: FeedbackRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FeedbackRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'feedbackRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $token = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $forceRealName = false;

    /** Fin des réponses (une semaine après la création, prolongeable) ; null pour les demandes antérieures au 24/11/2026. */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, CommunityFeedback>
     */
    #[ORM\OneToMany(mappedBy: 'feedbackRequest', targetEntity: CommunityFeedback::class, cascade: ['persist', 'remove'])]
    private Collection $communityFeedbacks;

    public function __construct()
    {
        $this->communityFeedbacks = new ArrayCollection();
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

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isForceRealName(): bool
    {
        return $this->forceRealName;
    }

    public function setForceRealName(bool $forceRealName): static
    {
        $this->forceRealName = $forceRealName;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(?\DateTimeImmutable $now = null): bool
    {
        return null !== $this->expiresAt && $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    /** Le lien accepte encore des réponses : ni fermé, ni expiré. */
    public function isOpen(?\DateTimeImmutable $now = null): bool
    {
        return $this->isActive && !$this->isExpired($now);
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, CommunityFeedback>
     */
    public function getCommunityFeedbacks(): Collection
    {
        return $this->communityFeedbacks;
    }

    public function addCommunityFeedback(CommunityFeedback $communityFeedback): static
    {
        if (!$this->communityFeedbacks->contains($communityFeedback)) {
            $this->communityFeedbacks->add($communityFeedback);
            $communityFeedback->setFeedbackRequest($this);
        }

        return $this;
    }

    public function removeCommunityFeedback(CommunityFeedback $communityFeedback): static
    {
        if ($this->communityFeedbacks->removeElement($communityFeedback)) {
            if ($communityFeedback->getFeedbackRequest() === $this) {
                $communityFeedback->setFeedbackRequest(null);
            }
        }

        return $this;
    }
}
