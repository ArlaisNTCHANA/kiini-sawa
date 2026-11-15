<?php

namespace App\Notification\Entity;

use App\Notification\Repository\NotificationPreferenceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: NotificationPreferenceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class NotificationPreference
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'notificationPreference', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column]
    private bool $enableInApp = true;

    #[ORM\Column]
    private bool $enableEmailInstant = false;

    #[ORM\Column]
    private bool $enableEmailDigest = true;

    #[ORM\Column]
    private bool $notifyConnection = true;

    #[ORM\Column]
    private bool $notifyChallenge = true;

    #[ORM\Column]
    private bool $notifyFeedback = true;

    #[ORM\Column]
    private bool $notifySuggestion = true;

    #[ORM\Column]
    private bool $notifyComment = true;

    #[ORM\Column]
    private bool $notifyMonthlyReview = true;

    #[ORM\Column(length: 10)]
    private string $preferredTime = '09:00';

    #[ORM\Column]
    private bool $notifyHabitReminder = true;

    #[ORM\Column]
    private bool $notifyGoalDeadline = true;

    #[ORM\Column]
    private bool $notifyWeeklyReview = true;

    #[ORM\Column]
    private int $weeklyReviewDay = 0;

    #[ORM\Column(length: 50)]
    private string $digestFrequency = 'WEEKLY';

    #[ORM\Column]
    private int $digestDay = 0;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function isEnableInApp(): bool
    {
        return $this->enableInApp;
    }

    public function setEnableInApp(bool $enableInApp): static
    {
        $this->enableInApp = $enableInApp;

        return $this;
    }

    public function isEnableEmailInstant(): bool
    {
        return $this->enableEmailInstant;
    }

    public function setEnableEmailInstant(bool $enableEmailInstant): static
    {
        $this->enableEmailInstant = $enableEmailInstant;

        return $this;
    }

    public function isEnableEmailDigest(): bool
    {
        return $this->enableEmailDigest;
    }

    public function setEnableEmailDigest(bool $enableEmailDigest): static
    {
        $this->enableEmailDigest = $enableEmailDigest;

        return $this;
    }

    public function isNotifyConnection(): bool
    {
        return $this->notifyConnection;
    }

    public function setNotifyConnection(bool $notifyConnection): static
    {
        $this->notifyConnection = $notifyConnection;

        return $this;
    }

    public function isNotifyChallenge(): bool
    {
        return $this->notifyChallenge;
    }

    public function setNotifyChallenge(bool $notifyChallenge): static
    {
        $this->notifyChallenge = $notifyChallenge;

        return $this;
    }

    public function isNotifyFeedback(): bool
    {
        return $this->notifyFeedback;
    }

    public function setNotifyFeedback(bool $notifyFeedback): static
    {
        $this->notifyFeedback = $notifyFeedback;

        return $this;
    }

    public function isNotifySuggestion(): bool
    {
        return $this->notifySuggestion;
    }

    public function setNotifySuggestion(bool $notifySuggestion): static
    {
        $this->notifySuggestion = $notifySuggestion;

        return $this;
    }

    public function isNotifyComment(): bool
    {
        return $this->notifyComment;
    }

    public function setNotifyComment(bool $notifyComment): static
    {
        $this->notifyComment = $notifyComment;

        return $this;
    }

    public function isNotifyMonthlyReview(): bool
    {
        return $this->notifyMonthlyReview;
    }

    public function setNotifyMonthlyReview(bool $notifyMonthlyReview): static
    {
        $this->notifyMonthlyReview = $notifyMonthlyReview;

        return $this;
    }

    public function getPreferredTime(): string
    {
        return $this->preferredTime;
    }

    public function setPreferredTime(string $preferredTime): static
    {
        $this->preferredTime = $preferredTime;

        return $this;
    }

    public function isNotifyHabitReminder(): bool
    {
        return $this->notifyHabitReminder;
    }

    public function setNotifyHabitReminder(bool $notifyHabitReminder): static
    {
        $this->notifyHabitReminder = $notifyHabitReminder;

        return $this;
    }

    public function isNotifyGoalDeadline(): bool
    {
        return $this->notifyGoalDeadline;
    }

    public function setNotifyGoalDeadline(bool $notifyGoalDeadline): static
    {
        $this->notifyGoalDeadline = $notifyGoalDeadline;

        return $this;
    }

    public function isNotifyWeeklyReview(): bool
    {
        return $this->notifyWeeklyReview;
    }

    public function setNotifyWeeklyReview(bool $notifyWeeklyReview): static
    {
        $this->notifyWeeklyReview = $notifyWeeklyReview;

        return $this;
    }

    public function getWeeklyReviewDay(): int
    {
        return $this->weeklyReviewDay;
    }

    public function setWeeklyReviewDay(int $weeklyReviewDay): static
    {
        $this->weeklyReviewDay = $weeklyReviewDay;

        return $this;
    }

    public function getDigestFrequency(): string
    {
        return $this->digestFrequency;
    }

    public function setDigestFrequency(string $digestFrequency): static
    {
        $this->digestFrequency = $digestFrequency;

        return $this;
    }

    public function getDigestDay(): int
    {
        return $this->digestDay;
    }

    public function setDigestDay(int $digestDay): static
    {
        $this->digestDay = $digestDay;

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
