<?php

namespace App\Entity;

use App\Repository\DailyLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DailyLogRepository::class)]
#[ORM\Table(name: 'daily_log')]
#[ORM\UniqueConstraint(name: 'uniq_daily_log_plan_date', columns: ['plan_dhygiene_id', 'date'])]
#[ORM\HasLifecycleCallbacks]
class DailyLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dailyLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanDHygiene $planDHygiene = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $sleepHours = null;

    #[ORM\Column(nullable: true)]
    private ?int $sleepQuality = null;

    #[ORM\Column(nullable: true)]
    private ?int $energyLevel = null;

    #[ORM\Column(nullable: true)]
    private ?int $stressLevel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $intention = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mood = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

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

    public function getPlanDHygiene(): ?PlanDHygiene
    {
        return $this->planDHygiene;
    }

    public function setPlanDHygiene(?PlanDHygiene $planDHygiene): static
    {
        $this->planDHygiene = $planDHygiene;

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

    public function getSleepHours(): ?float
    {
        return $this->sleepHours;
    }

    public function setSleepHours(?float $sleepHours): static
    {
        $this->sleepHours = $sleepHours;

        return $this;
    }

    public function getSleepQuality(): ?int
    {
        return $this->sleepQuality;
    }

    public function setSleepQuality(?int $sleepQuality): static
    {
        $this->sleepQuality = $sleepQuality;

        return $this;
    }

    public function getEnergyLevel(): ?int
    {
        return $this->energyLevel;
    }

    public function setEnergyLevel(?int $energyLevel): static
    {
        $this->energyLevel = $energyLevel;

        return $this;
    }

    public function getStressLevel(): ?int
    {
        return $this->stressLevel;
    }

    public function setStressLevel(?int $stressLevel): static
    {
        $this->stressLevel = $stressLevel;

        return $this;
    }

    public function getIntention(): ?string
    {
        return $this->intention;
    }

    public function setIntention(?string $intention): static
    {
        $this->intention = $intention;

        return $this;
    }

    public function getMood(): ?string
    {
        return $this->mood;
    }

    public function setMood(?string $mood): static
    {
        $this->mood = $mood;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

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
