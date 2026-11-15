<?php

namespace App\Rituels\Entity;

use App\Rituels\Enum\HabitFrequency;
use App\Rituels\Repository\HabitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;
use App\PlanDHygiene\Entity\HygieneDomain;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeDeveloppement\Entity\SkillGoal;
use App\PlanDeTransmission\Entity\LegacyGoal;
use App\PlanDeVie\Entity\LifeGoal;

#[ORM\Entity(repositoryClass: HabitRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Habit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'habits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'habits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LifeGoal $lifeGoal = null;

    #[ORM\ManyToOne(inversedBy: 'habits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GrowthGoal $growthGoal = null;

    #[ORM\ManyToOne(inversedBy: 'habits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SkillGoal $skillGoal = null;

    #[ORM\ManyToOne(inversedBy: 'habits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?HygieneDomain $hygieneDomain = null;

    #[ORM\ManyToOne(inversedBy: 'habits')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LegacyGoal $legacyGoal = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 50, type: 'string', enumType: HabitFrequency::class)]
    private HabitFrequency $frequency = HabitFrequency::DAILY;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(nullable: true)]
    private ?int $recurrenceDay = null;

    #[ORM\Column(nullable: true)]
    private ?int $recurrenceWeek = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $implementationWhen = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $implementationThen = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $supportStrategy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rootingIntention = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rootingAction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rootingMoment = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rootingAnchor = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rootingBenefit = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rootingFacilitator = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, HabitLog>
     */
    #[ORM\OneToMany(mappedBy: 'habit', targetEntity: HabitLog::class, cascade: ['persist', 'remove'])]
    private Collection $logs;

    public function __construct()
    {
        $this->logs = new ArrayCollection();
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

    public function getLifeGoal(): ?LifeGoal
    {
        return $this->lifeGoal;
    }

    public function setLifeGoal(?LifeGoal $lifeGoal): static
    {
        $this->lifeGoal = $lifeGoal;

        return $this;
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

    public function getSkillGoal(): ?SkillGoal
    {
        return $this->skillGoal;
    }

    public function setSkillGoal(?SkillGoal $skillGoal): static
    {
        $this->skillGoal = $skillGoal;

        return $this;
    }

    public function getHygieneDomain(): ?HygieneDomain
    {
        return $this->hygieneDomain;
    }

    public function setHygieneDomain(?HygieneDomain $hygieneDomain): static
    {
        $this->hygieneDomain = $hygieneDomain;

        return $this;
    }

    public function getLegacyGoal(): ?LegacyGoal
    {
        return $this->legacyGoal;
    }

    public function setLegacyGoal(?LegacyGoal $legacyGoal): static
    {
        $this->legacyGoal = $legacyGoal;

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

    public function getFrequency(): HabitFrequency
    {
        return $this->frequency;
    }

    public function setFrequency(HabitFrequency $frequency): static
    {
        $this->frequency = $frequency;

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

    public function getRecurrenceDay(): ?int
    {
        return $this->recurrenceDay;
    }

    public function setRecurrenceDay(?int $recurrenceDay): static
    {
        $this->recurrenceDay = $recurrenceDay;

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

    public function getImplementationWhen(): ?string
    {
        return $this->implementationWhen;
    }

    public function setImplementationWhen(?string $implementationWhen): static
    {
        $this->implementationWhen = $implementationWhen;

        return $this;
    }

    public function getImplementationThen(): ?string
    {
        return $this->implementationThen;
    }

    public function setImplementationThen(?string $implementationThen): static
    {
        $this->implementationThen = $implementationThen;

        return $this;
    }

    public function getSupportStrategy(): ?string
    {
        return $this->supportStrategy;
    }

    public function setSupportStrategy(?string $supportStrategy): static
    {
        $this->supportStrategy = $supportStrategy;

        return $this;
    }

    public function getRootingIntention(): ?string
    {
        return $this->rootingIntention;
    }

    public function setRootingIntention(?string $rootingIntention): static
    {
        $this->rootingIntention = $rootingIntention;

        return $this;
    }

    public function getRootingAction(): ?string
    {
        return $this->rootingAction;
    }

    public function setRootingAction(?string $rootingAction): static
    {
        $this->rootingAction = $rootingAction;

        return $this;
    }

    public function getRootingMoment(): ?string
    {
        return $this->rootingMoment;
    }

    public function setRootingMoment(?string $rootingMoment): static
    {
        $this->rootingMoment = $rootingMoment;

        return $this;
    }

    public function getRootingAnchor(): ?string
    {
        return $this->rootingAnchor;
    }

    public function setRootingAnchor(?string $rootingAnchor): static
    {
        $this->rootingAnchor = $rootingAnchor;

        return $this;
    }

    public function getRootingBenefit(): ?string
    {
        return $this->rootingBenefit;
    }

    public function setRootingBenefit(?string $rootingBenefit): static
    {
        $this->rootingBenefit = $rootingBenefit;

        return $this;
    }

    public function getRootingFacilitator(): ?string
    {
        return $this->rootingFacilitator;
    }

    public function setRootingFacilitator(?string $rootingFacilitator): static
    {
        $this->rootingFacilitator = $rootingFacilitator;

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

    /**
     * @return Collection<int, HabitLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(HabitLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setHabit($this);
        }

        return $this;
    }

    public function removeLog(HabitLog $log): static
    {
        if ($this->logs->removeElement($log)) {
            // set the owning side to null (unless already changed)
            if ($log->getHabit() === $this) {
                $log->setHabit(null);
            }
        }

        return $this;
    }

    public function isCompletedForCurrentPeriod(): bool
    {
        $today = new \DateTime('today');
        
        $latestLog = null;
        foreach ($this->getLogs() as $log) {
            if ($latestLog === null || $log->getDate() > $latestLog->getDate()) {
                $latestLog = $log;
            }
        }
        
        if ($latestLog === null || !$latestLog->isStatus()) {
            return false;
        }
        
        $latestLogDate = $latestLog->getDate();
        $freq = $this->getFrequency()->value;
        
        if ($freq === 'DAILY') {
            return $latestLogDate->format('Y-m-d') === $today->format('Y-m-d');
        }
        
        if ($freq === 'WEEKLY') {
            $startOfWeek = new \DateTime('monday this week');
            return $latestLogDate >= $startOfWeek;
        }
        
        if ($freq === 'MONTHLY') {
            $startOfMonth = new \DateTime('first day of this month 00:00:00');
            return $latestLogDate >= $startOfMonth;
        }
        
        return $latestLogDate->format('Y-m-d') === $today->format('Y-m-d');
    }
}

