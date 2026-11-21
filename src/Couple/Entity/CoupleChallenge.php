<?php

namespace App\Couple\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Défi de couple actif ou terminé (ex. 7 jours d'écoute bienveillante).
 */
#[ORM\Entity]
class CoupleChallenge
{
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_ABANDONED = 'ABANDONED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Couple $couple;

    #[ORM\Column(length: 60)]
    private string $challengeCode;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private int $currentDay = 1;

    #[ORM\Column]
    private int $totalDays;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** @var Collection<int, CoupleChallengeDay> */
    #[ORM\OneToMany(mappedBy: 'challenge', targetEntity: CoupleChallengeDay::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['dayNumber' => 'ASC'])]
    private Collection $days;

    public function __construct(Couple $couple, string $challengeCode, int $totalDays)
    {
        $this->couple = $couple;
        $this->challengeCode = $challengeCode;
        $this->totalDays = max(1, $totalDays);
        $this->startedAt = new \DateTimeImmutable();
        $this->days = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCouple(): Couple { return $this->couple; }
    public function getChallengeCode(): string { return $this->challengeCode; }
    public function getStatus(): string { return $this->status; }
    public function isActive(): bool { return self::STATUS_ACTIVE === $this->status; }
    public function getCurrentDay(): int { return $this->currentDay; }
    public function getTotalDays(): int { return $this->totalDays; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }

    /** @return Collection<int, CoupleChallengeDay> */
    public function getDays(): Collection { return $this->days; }

    public function getDay(int $dayNumber): ?CoupleChallengeDay
    {
        foreach ($this->days as $day) {
            if ($day->getDayNumber() === $dayNumber) {
                return $day;
            }
        }

        return null;
    }

    public function getOrCreateDay(int $dayNumber): CoupleChallengeDay
    {
        $existing = $this->getDay($dayNumber);
        if (null !== $existing) {
            return $existing;
        }
        $day = new CoupleChallengeDay($this, $dayNumber);
        $this->days->add($day);

        return $day;
    }

    public function advanceDay(): void
    {
        if ($this->currentDay >= $this->totalDays) {
            $this->status = self::STATUS_COMPLETED;
            $this->completedAt = new \DateTimeImmutable();
        } else {
            ++$this->currentDay;
        }
    }

    public function abandon(): void
    {
        $this->status = self::STATUS_ABANDONED;
    }
}
