<?php

namespace App\Defis\Entity;

use App\Defis\Repository\ChallengeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: ChallengeRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Challenge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private string $type = 'HABIT';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $habitTitle = null;

    #[ORM\Column(nullable: true)]
    private ?int $targetValue = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\ManyToOne(inversedBy: 'createdChallenges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $creator = null;

    #[ORM\Column(length: 50)]
    private string $frequency = 'DAILY';

    #[ORM\Column(length: 50)]
    private string $visibility = 'PARTNERS';

    #[ORM\Column(nullable: true)]
    private ?int $recurrenceDay = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $recurrenceDays = null;

    #[ORM\Column(nullable: true)]
    private ?int $recurrenceWeek = null;

    #[ORM\Column(nullable: true)]
    private ?int $recurrenceOrdinal = null;

    #[ORM\OneToMany(mappedBy: 'challenge', targetEntity: ChallengeParticipant::class, cascade: ['persist', 'remove'])]
    private Collection $participants;

    #[ORM\OneToMany(mappedBy: 'challenge', targetEntity: ChallengeInvitation::class, cascade: ['persist', 'remove'])]
    private Collection $invitations;

    #[ORM\OneToMany(mappedBy: 'challenge', targetEntity: ChallengeActivity::class, cascade: ['persist', 'remove'])]
    private Collection $activities;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->startDate = new \DateTime();
        $this->createdAt = new \DateTime();
        $this->participants = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->activities = new ArrayCollection();
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

    public function setDescription(?string $description): static
    {
        $this->description = $description;

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

    public function getHabitTitle(): ?string
    {
        return $this->habitTitle;
    }

    public function setHabitTitle(?string $habitTitle): static
    {
        $this->habitTitle = $habitTitle;

        return $this;
    }

    public function getTargetValue(): ?int
    {
        return $this->targetValue;
    }

    public function setTargetValue(?int $targetValue): static
    {
        $this->targetValue = $targetValue;

        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getCreator(): ?User
    {
        return $this->creator;
    }

    public function setCreator(?User $creator): static
    {
        $this->creator = $creator;

        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = $frequency;

        return $this;
    }

    public function getVisibility(): string
    {
        return $this->visibility;
    }

    public function setVisibility(string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function getRecurrenceDay(): ?int
    {
        return $this->recurrenceDay;
    }

    public function setRecurrenceDay(?int $recurrenceDay): static
    {
        $this->recurrenceDay = $recurrenceDay;

        return $this;
    }

    public function getRecurrenceDays(): ?string
    {
        return $this->recurrenceDays;
    }

    public function setRecurrenceDays(?string $recurrenceDays): static
    {
        $this->recurrenceDays = $recurrenceDays;

        return $this;
    }

    public function getRecurrenceWeek(): ?int
    {
        return $this->recurrenceWeek;
    }

    public function setRecurrenceWeek(?int $recurrenceWeek): static
    {
        $this->recurrenceWeek = $recurrenceWeek;

        return $this;
    }

    public function getRecurrenceOrdinal(): ?int
    {
        return $this->recurrenceOrdinal;
    }

    public function setRecurrenceOrdinal(?int $recurrenceOrdinal): static
    {
        $this->recurrenceOrdinal = $recurrenceOrdinal;

        return $this;
    }

    /**
     * @return Collection<int, ChallengeParticipant>
     */
    public function getParticipants(): Collection
    {
        return $this->participants;
    }

    public function addParticipant(ChallengeParticipant $participant): static
    {
        if (!$this->participants->contains($participant)) {
            $this->participants->add($participant);
            $participant->setChallenge($this);
        }

        return $this;
    }

    public function removeParticipant(ChallengeParticipant $participant): static
    {
        if ($this->participants->removeElement($participant)) {
            if ($participant->getChallenge() === $this) {
                $participant->setChallenge(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChallengeInvitation>
     */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function addInvitation(ChallengeInvitation $invitation): static
    {
        if (!$this->invitations->contains($invitation)) {
            $this->invitations->add($invitation);
            $invitation->setChallenge($this);
        }

        return $this;
    }

    public function removeInvitation(ChallengeInvitation $invitation): static
    {
        if ($this->invitations->removeElement($invitation)) {
            if ($invitation->getChallenge() === $this) {
                $invitation->setChallenge(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChallengeActivity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function addActivity(ChallengeActivity $activity): static
    {
        if (!$this->activities->contains($activity)) {
            $this->activities->add($activity);
            $activity->setChallenge($this);
        }

        return $this;
    }

    public function removeActivity(ChallengeActivity $activity): static
    {
        if ($this->activities->removeElement($activity)) {
            if ($activity->getChallenge() === $this) {
                $activity->setChallenge(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
