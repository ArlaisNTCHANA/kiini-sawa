<?php

namespace App\PlanDeCroissance\Entity;

use App\PlanDeCroissance\Enum\CharacterAction;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDeCroissance\Repository\GrowthGoalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Defis\Entity\ChallengeParticipant;
use App\Journal\Entity\ReflectionNote;
use App\PlanDeVie\Entity\LifeGoal;
use App\Planner\Entity\PlannerTask;
use App\Rituels\Entity\Habit;

#[ORM\Entity(repositoryClass: GrowthGoalRepository::class)]
#[ORM\HasLifecycleCallbacks]
class GrowthGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'growthGoals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanDeCroissance $planDeCroissance = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(nullable: true)]
    private ?int $sourceId = null;

    #[ORM\Column(length: 50, type: 'string', enumType: GrowthAxis::class)]
    private GrowthAxis $axis;

    #[ORM\Column(length: 50, type: 'string', enumType: CharacterAction::class, nullable: true)]
    private ?CharacterAction $characterAction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $strategy = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $subtype = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metric = null;

    #[ORM\Column]
    private int $progress = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $why = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, GrowthAction>
     */
    #[ORM\OneToMany(mappedBy: 'growthGoal', targetEntity: GrowthAction::class, cascade: ['persist', 'remove'])]
    private Collection $actions;

    /**
     * @var Collection<int, GrowthGoalScore>
     */
    #[ORM\OneToMany(mappedBy: 'growthGoal', targetEntity: GrowthGoalScore::class, cascade: ['persist', 'remove'])]
    private Collection $scores;

    /**
     * @var Collection<int, Habit>
     */
    #[ORM\OneToMany(mappedBy: 'growthGoal', targetEntity: Habit::class)]
    private Collection $habits;

    /**
     * @var Collection<int, LifeGoal>
     */
    #[ORM\ManyToMany(targetEntity: LifeGoal::class, inversedBy: 'supportingGrowthGoals')]
    #[ORM\JoinTable(name: 'growth_goal_life_goal')]
    private Collection $supportedLifeGoals;

    /**
     * @var Collection<int, ReflectionNote>
     */
    #[ORM\OneToMany(mappedBy: 'growthGoal', targetEntity: ReflectionNote::class)]
    private Collection $reflectionNotes;

    /**
     * @var Collection<int, PlannerTask>
     */
    #[ORM\OneToMany(mappedBy: 'growthGoal', targetEntity: PlannerTask::class)]
    private Collection $plannerTasks;

    /**
     * @var Collection<int, ChallengeParticipant>
     */
    #[ORM\OneToMany(mappedBy: 'linkedGrowthGoal', targetEntity: ChallengeParticipant::class)]
    private Collection $challengeParticipations;

    public function __construct()
    {
        $this->actions = new ArrayCollection();
        $this->scores = new ArrayCollection();
        $this->habits = new ArrayCollection();
        $this->supportedLifeGoals = new ArrayCollection();
        $this->reflectionNotes = new ArrayCollection();
        $this->plannerTasks = new ArrayCollection();
        $this->challengeParticipations = new ArrayCollection();
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

    public function getPlanDeCroissance(): ?PlanDeCroissance
    {
        return $this->planDeCroissance;
    }

    public function setPlanDeCroissance(?PlanDeCroissance $planDeCroissance): static
    {
        $this->planDeCroissance = $planDeCroissance;

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

    public function getAxis(): GrowthAxis
    {
        return $this->axis;
    }

    public function setAxis(GrowthAxis $axis): static
    {
        $this->axis = $axis;

        return $this;
    }

    public function getCharacterAction(): ?CharacterAction
    {
        return $this->characterAction;
    }

    public function setCharacterAction(?CharacterAction $characterAction): static
    {
        $this->characterAction = $characterAction;

        return $this;
    }

    public function getStrategy(): ?string
    {
        return $this->strategy;
    }

    public function setStrategy(string $strategy): static
    {
        $this->strategy = $strategy;

        return $this;
    }

    public function getMetric(): ?string
    {
        return $this->metric;
    }

    public function setMetric(string $metric): static
    {
        $this->metric = $metric;

        return $this;
    }

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;

        return $this;
    }

    public function getWhy(): ?string
    {
        return $this->why;
    }

    public function setWhy(?string $why): static
    {
        $this->why = $why;

        return $this;
    }

    public function getDeadline(): ?\DateTimeInterface
    {
        return $this->deadline;
    }

    public function setDeadline(?\DateTimeInterface $deadline): static
    {
        $this->deadline = $deadline;

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
     * @return Collection<int, GrowthAction>
     */
    public function getActions(): Collection
    {
        return $this->actions;
    }

    public function addAction(GrowthAction $action): static
    {
        if (!$this->actions->contains($action)) {
            $this->actions->add($action);
            $action->setGrowthGoal($this);
        }

        return $this;
    }

    public function removeAction(GrowthAction $action): static
    {
        if ($this->actions->removeElement($action)) {
            // set the owning side to null (unless already changed)
            if ($action->getGrowthGoal() === $this) {
                $action->setGrowthGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GrowthGoalScore>
     */
    public function getScores(): Collection
    {
        return $this->scores;
    }

    public function addScore(GrowthGoalScore $score): static
    {
        if (!$this->scores->contains($score)) {
            $this->scores->add($score);
            $score->setGrowthGoal($this);
        }

        return $this;
    }

    public function removeScore(GrowthGoalScore $score): static
    {
        if ($this->scores->removeElement($score)) {
            // set the owning side to null (unless already changed)
            if ($score->getGrowthGoal() === $this) {
                $score->setGrowthGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Habit>
     */
    public function getHabits(): Collection
    {
        return $this->habits;
    }

    public function addHabit(Habit $habit): static
    {
        if (!$this->habits->contains($habit)) {
            $this->habits->add($habit);
            $habit->setGrowthGoal($this);
        }

        return $this;
    }

    public function removeHabit(Habit $habit): static
    {
        if ($this->habits->removeElement($habit)) {
            // set the owning side to null (unless already changed)
            if ($habit->getGrowthGoal() === $this) {
                $habit->setGrowthGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LifeGoal>
     */
    public function getSupportedLifeGoals(): Collection
    {
        return $this->supportedLifeGoals;
    }

    public function addSupportedLifeGoal(LifeGoal $lifeGoal): static
    {
        if (!$this->supportedLifeGoals->contains($lifeGoal)) {
            $this->supportedLifeGoals->add($lifeGoal);
            $lifeGoal->addSupportingGrowthGoal($this); // Will add this helper method to LifeGoal
        }

        return $this;
    }

    public function removeSupportedLifeGoal(LifeGoal $lifeGoal): static
    {
        if ($this->supportedLifeGoals->removeElement($lifeGoal)) {
            $lifeGoal->removeSupportingGrowthGoal($this); // Will add this helper method to LifeGoal
        }

        return $this;
    }

    /**
     * @return Collection<int, ReflectionNote>
     */
    public function getReflectionNotes(): Collection
    {
        return $this->reflectionNotes;
    }

    public function addReflectionNote(ReflectionNote $reflectionNote): static
    {
        if (!$this->reflectionNotes->contains($reflectionNote)) {
            $this->reflectionNotes->add($reflectionNote);
            $reflectionNote->setGrowthGoal($this);
        }

        return $this;
    }

    public function removeReflectionNote(ReflectionNote $reflectionNote): static
    {
        if ($this->reflectionNotes->removeElement($reflectionNote)) {
            if ($reflectionNote->getGrowthGoal() === $this) {
                $reflectionNote->setGrowthGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, PlannerTask>
     */
    public function getPlannerTasks(): Collection
    {
        return $this->plannerTasks;
    }

    public function addPlannerTask(PlannerTask $plannerTask): static
    {
        if (!$this->plannerTasks->contains($plannerTask)) {
            $this->plannerTasks->add($plannerTask);
            $plannerTask->setGrowthGoal($this);
        }

        return $this;
    }

    public function removePlannerTask(PlannerTask $plannerTask): static
    {
        if ($this->plannerTasks->removeElement($plannerTask)) {
            if ($plannerTask->getGrowthGoal() === $this) {
                $plannerTask->setGrowthGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ChallengeParticipant>
     */
    public function getChallengeParticipations(): Collection
    {
        return $this->challengeParticipations;
    }

    public function addChallengeParticipation(ChallengeParticipant $participant): static
    {
        if (!$this->challengeParticipations->contains($participant)) {
            $this->challengeParticipations->add($participant);
            $participant->setLinkedGrowthGoal($this);
        }

        return $this;
    }

    public function removeChallengeParticipation(ChallengeParticipant $participant): static
    {
        if ($this->challengeParticipations->removeElement($participant)) {
            if ($participant->getLinkedGrowthGoal() === $this) {
                $participant->setLinkedGrowthGoal(null);
            }
        }

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    public function setSubtype(?string $subtype): static
    {
        $this->subtype = $subtype;

        return $this;
    }

    public function getSourceType(): ?string
    {
        return $this->sourceType;
    }

    public function setSourceType(?string $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceId(): ?int
    {
        return $this->sourceId;
    }

    public function setSourceId(?int $sourceId): static
    {
        $this->sourceId = $sourceId;

        return $this;
    }
}
