<?php

namespace App\Couple\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Parcours de méditation / prière partagé par le couple.
 */
#[ORM\Entity]
class CoupleMeditation
{
    public const STATUS_IN_PROGRESS = 'IN_PROGRESS';
    public const STATUS_FINISHED = 'FINISHED';
    public const STATUS_ABANDONED = 'ABANDONED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Couple $couple;

    #[ORM\Column(length: 60)]
    private string $planCode;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_IN_PROGRESS;

    #[ORM\Column]
    private int $currentStep = 1;

    #[ORM\Column]
    private int $totalSteps;

    #[ORM\Column]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSessionAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    public function __construct(Couple $couple, string $planCode, int $totalSteps)
    {
        $this->couple = $couple;
        $this->planCode = $planCode;
        $this->totalSteps = max(1, $totalSteps);
        $this->startedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCouple(): Couple { return $this->couple; }
    public function getPlanCode(): string { return $this->planCode; }
    public function getStatus(): string { return $this->status; }
    public function isInProgress(): bool { return self::STATUS_IN_PROGRESS === $this->status; }
    public function getCurrentStep(): int { return $this->currentStep; }
    public function getTotalSteps(): int { return $this->totalSteps; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getLastSessionAt(): ?\DateTimeImmutable { return $this->lastSessionAt; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }

    public function completeCurrentStep(): void
    {
        $now = new \DateTimeImmutable();
        $this->lastSessionAt = $now;
        if ($this->currentStep >= $this->totalSteps) {
            $this->status = self::STATUS_FINISHED;
            $this->completedAt = $now;
        } else {
            ++$this->currentStep;
        }
    }

    public function abandon(): void
    {
        $this->status = self::STATUS_ABANDONED;
    }
}
