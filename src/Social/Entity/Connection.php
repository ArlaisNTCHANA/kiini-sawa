<?php

namespace App\Social\Entity;

use App\Social\Enum\ShareScope;
use App\Social\Repository\ConnectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: ConnectionRepository::class)]
#[ORM\Table(name: 'connection')]
#[ORM\UniqueConstraint(name: 'UNIQ_REQUESTER_RECEIVER', fields: ['requester', 'receiver'])]
#[ORM\HasLifecycleCallbacks]
class Connection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'connections')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $requester = null;

    #[ORM\ManyToOne(inversedBy: 'connectedTo')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $receiver = null;

    #[ORM\Column(length: 50)]
    private string $status = 'PENDING';

    #[ORM\Column(length: 50)]
    private string $type = 'FRIEND';

    #[ORM\Column(length: 50, type: 'string', enumType: ShareScope::class)]
    private ShareScope $shareScope = ShareScope::TITLES_ONLY;

    #[ORM\Column]
    private bool $requesterShareLifePlan = true;

    #[ORM\Column]
    private bool $requesterShareGrowthPlan = true;

    #[ORM\Column]
    private bool $requesterShareHabits = true;

    #[ORM\Column]
    private bool $requesterShareReviews = false;

    #[ORM\Column]
    private bool $requesterShareDiscovery = false;

    #[ORM\Column]
    private bool $requesterShareCroissance = true;

    #[ORM\Column]
    private bool $requesterShareDeveloppement = true;

    #[ORM\Column]
    private bool $requesterShareHygiene = true;

    #[ORM\Column]
    private bool $requesterShareTransmission = true;

    #[ORM\Column]
    private bool $requesterShareBilan = true;

    #[ORM\Column]
    private bool $receiverShareLifePlan = true;

    #[ORM\Column]
    private bool $receiverShareGrowthPlan = true;

    #[ORM\Column]
    private bool $receiverShareHabits = true;

    #[ORM\Column]
    private bool $receiverShareReviews = false;

    #[ORM\Column]
    private bool $receiverShareDiscovery = false;

    #[ORM\Column]
    private bool $receiverShareCroissance = true;

    #[ORM\Column]
    private bool $receiverShareDeveloppement = true;

    #[ORM\Column]
    private bool $receiverShareHygiene = true;

    #[ORM\Column]
    private bool $receiverShareTransmission = true;

    #[ORM\Column]
    private bool $receiverShareBilan = true;

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

    public function getRequester(): ?User
    {
        return $this->requester;
    }

    public function setRequester(?User $requester): static
    {
        $this->requester = $requester;

        return $this;
    }

    public function getReceiver(): ?User
    {
        return $this->receiver;
    }

    public function setReceiver(?User $receiver): static
    {
        $this->receiver = $receiver;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getShareScope(): ShareScope
    {
        return $this->shareScope;
    }

    public function setShareScope(ShareScope $shareScope): static
    {
        $this->shareScope = $shareScope;

        return $this;
    }

    public function isRequesterShareLifePlan(): bool
    {
        return $this->requesterShareLifePlan;
    }

    public function setRequesterShareLifePlan(bool $requesterShareLifePlan): static
    {
        $this->requesterShareLifePlan = $requesterShareLifePlan;

        return $this;
    }

    public function isRequesterShareGrowthPlan(): bool
    {
        return $this->requesterShareGrowthPlan;
    }

    public function setRequesterShareGrowthPlan(bool $requesterShareGrowthPlan): static
    {
        $this->requesterShareGrowthPlan = $requesterShareGrowthPlan;

        return $this;
    }

    public function isRequesterShareHabits(): bool
    {
        return $this->requesterShareHabits;
    }

    public function setRequesterShareHabits(bool $requesterShareHabits): static
    {
        $this->requesterShareHabits = $requesterShareHabits;

        return $this;
    }

    public function isRequesterShareReviews(): bool
    {
        return $this->requesterShareReviews;
    }

    public function setRequesterShareReviews(bool $requesterShareReviews): static
    {
        $this->requesterShareReviews = $requesterShareReviews;

        return $this;
    }

    public function isRequesterShareDiscovery(): bool
    {
        return $this->requesterShareDiscovery;
    }

    public function setRequesterShareDiscovery(bool $requesterShareDiscovery): static
    {
        $this->requesterShareDiscovery = $requesterShareDiscovery;

        return $this;
    }

    public function isRequesterShareCroissance(): bool
    {
        return $this->requesterShareCroissance;
    }

    public function setRequesterShareCroissance(bool $requesterShareCroissance): static
    {
        $this->requesterShareCroissance = $requesterShareCroissance;

        return $this;
    }

    public function isRequesterShareDeveloppement(): bool
    {
        return $this->requesterShareDeveloppement;
    }

    public function setRequesterShareDeveloppement(bool $requesterShareDeveloppement): static
    {
        $this->requesterShareDeveloppement = $requesterShareDeveloppement;

        return $this;
    }

    public function isRequesterShareHygiene(): bool
    {
        return $this->requesterShareHygiene;
    }

    public function setRequesterShareHygiene(bool $requesterShareHygiene): static
    {
        $this->requesterShareHygiene = $requesterShareHygiene;

        return $this;
    }

    public function isRequesterShareTransmission(): bool
    {
        return $this->requesterShareTransmission;
    }

    public function setRequesterShareTransmission(bool $requesterShareTransmission): static
    {
        $this->requesterShareTransmission = $requesterShareTransmission;

        return $this;
    }

    public function isRequesterShareBilan(): bool
    {
        return $this->requesterShareBilan;
    }

    public function setRequesterShareBilan(bool $requesterShareBilan): static
    {
        $this->requesterShareBilan = $requesterShareBilan;

        return $this;
    }

    public function isReceiverShareLifePlan(): bool
    {
        return $this->receiverShareLifePlan;
    }

    public function setReceiverShareLifePlan(bool $receiverShareLifePlan): static
    {
        $this->receiverShareLifePlan = $receiverShareLifePlan;

        return $this;
    }

    public function isReceiverShareGrowthPlan(): bool
    {
        return $this->receiverShareGrowthPlan;
    }

    public function setReceiverShareGrowthPlan(bool $receiverShareGrowthPlan): static
    {
        $this->receiverShareGrowthPlan = $receiverShareGrowthPlan;

        return $this;
    }

    public function isReceiverShareHabits(): bool
    {
        return $this->receiverShareHabits;
    }

    public function setReceiverShareHabits(bool $receiverShareHabits): static
    {
        $this->receiverShareHabits = $receiverShareHabits;

        return $this;
    }

    public function isReceiverShareReviews(): bool
    {
        return $this->receiverShareReviews;
    }

    public function setReceiverShareReviews(bool $receiverShareReviews): static
    {
        $this->receiverShareReviews = $receiverShareReviews;

        return $this;
    }

    public function isReceiverShareDiscovery(): bool
    {
        return $this->receiverShareDiscovery;
    }

    public function setReceiverShareDiscovery(bool $receiverShareDiscovery): static
    {
        $this->receiverShareDiscovery = $receiverShareDiscovery;

        return $this;
    }

    public function isReceiverShareCroissance(): bool
    {
        return $this->receiverShareCroissance;
    }

    public function setReceiverShareCroissance(bool $receiverShareCroissance): static
    {
        $this->receiverShareCroissance = $receiverShareCroissance;

        return $this;
    }

    public function isReceiverShareDeveloppement(): bool
    {
        return $this->receiverShareDeveloppement;
    }

    public function setReceiverShareDeveloppement(bool $receiverShareDeveloppement): static
    {
        $this->receiverShareDeveloppement = $receiverShareDeveloppement;

        return $this;
    }

    public function isReceiverShareHygiene(): bool
    {
        return $this->receiverShareHygiene;
    }

    public function setReceiverShareHygiene(bool $receiverShareHygiene): static
    {
        $this->receiverShareHygiene = $receiverShareHygiene;

        return $this;
    }

    public function isReceiverShareTransmission(): bool
    {
        return $this->receiverShareTransmission;
    }

    public function setReceiverShareTransmission(bool $receiverShareTransmission): static
    {
        $this->receiverShareTransmission = $receiverShareTransmission;

        return $this;
    }

    public function isReceiverShareBilan(): bool
    {
        return $this->receiverShareBilan;
    }

    public function setReceiverShareBilan(bool $receiverShareBilan): static
    {
        $this->receiverShareBilan = $receiverShareBilan;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
