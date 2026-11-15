<?php

namespace App\PlanDeCroissance\Entity;

use App\PlanDeCroissance\Repository\GrowthGoalScoreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: GrowthGoalScoreRepository::class)]
#[ORM\HasLifecycleCallbacks]
class GrowthGoalScore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'scores')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?GrowthGoal $growthGoal = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column]
    private int $progressValue = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->date === null) {
            $this->date = new \DateTime();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function getProgressValue(): int
    {
        return $this->progressValue;
    }

    public function setProgressValue(int $progressValue): static
    {
        $this->progressValue = $progressValue;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

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
}
