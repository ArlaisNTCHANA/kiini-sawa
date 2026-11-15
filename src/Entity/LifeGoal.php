<?php

namespace App\Entity;

use App\Repository\LifeGoalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LifeGoalRepository::class)]
class LifeGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lifeGoals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanDeVie $planDeVie = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 50)]
    private ?string $category = 'DO'; // BE, DO, HAVE, GIVE

    #[ORM\Column(length: 50)]
    private ?string $priority = 'MEDIUM'; // LOW, MEDIUM, HIGH

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $metric = null;

    #[ORM\Column]
    private int $progress = 0;

    #[ORM\Column]
    private bool $isShared = false;

    /**
     * @var Collection<int, LifeMilestone>
     */
    #[ORM\OneToMany(mappedBy: 'lifeGoal', targetEntity: LifeMilestone::class, cascade: ['persist', 'remove'])]
    private Collection $milestones;

    /**
     * @var Collection<int, Habit>
     */
    #[ORM\OneToMany(mappedBy: 'lifeGoal', targetEntity: Habit::class)]
    private Collection $habits;

    /**
     * @var Collection<int, GrowthGoal>
     */
    #[ORM\ManyToMany(targetEntity: GrowthGoal::class, mappedBy: 'supportedLifeGoals')]
    private Collection $supportingGrowthGoals;

    /**
     * @var Collection<int, SkillGoal>
     */
    #[ORM\ManyToMany(targetEntity: SkillGoal::class, mappedBy: 'supportedLifeGoals')]
    private Collection $supportingSkillGoals;

    /**
     * @var Collection<int, LegacyGoal>
     */
    #[ORM\ManyToMany(targetEntity: LegacyGoal::class, mappedBy: 'supportedLifeGoals')]
    private Collection $supportingLegacyGoals;

    /**
     * @var Collection<int, HygieneDomain>
     */
    #[ORM\ManyToMany(targetEntity: HygieneDomain::class, mappedBy: 'supportedLifeGoals')]
    private Collection $supportingHygieneDomains;

    /**
     * @var Collection<int, ReflectionNote>
     */
    #[ORM\OneToMany(mappedBy: 'goal', targetEntity: ReflectionNote::class)]
    private Collection $reflectionNotes;

    /**
     * @var Collection<int, ListItem>
     */
    #[ORM\OneToMany(mappedBy: 'goal', targetEntity: ListItem::class)]
    private Collection $listItems;

    /**
     * @var Collection<int, ChallengeParticipant>
     */
    #[ORM\OneToMany(mappedBy: 'linkedGoal', targetEntity: ChallengeParticipant::class)]
    private Collection $challengeParticipations;

    public function __construct()
    {
        $this->milestones = new ArrayCollection();
        $this->habits = new ArrayCollection();
        $this->supportingGrowthGoals = new ArrayCollection();
        $this->supportingSkillGoals = new ArrayCollection();
        $this->supportingLegacyGoals = new ArrayCollection();
        $this->supportingHygieneDomains = new ArrayCollection();
        $this->reflectionNotes = new ArrayCollection();
        $this->listItems = new ArrayCollection();
        $this->challengeParticipations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanDeVie(): ?PlanDeVie
    {
        return $this->planDeVie;
    }

    public function setPlanDeVie(?PlanDeVie $planDeVie): static
    {
        $this->planDeVie = $planDeVie;

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

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;

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

    public function getMetric(): ?string
    {
        return $this->metric;
    }

    public function setMetric(?string $metric): static
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

    public function isShared(): bool
    {
        return $this->isShared;
    }

    public function setIsShared(bool $isShared): static
    {
        $this->isShared = $isShared;

        return $this;
    }

    /**
     * @return Collection<int, LifeMilestone>
     */
    public function getMilestones(): Collection
    {
        return $this->milestones;
    }

    public function addMilestone(LifeMilestone $milestone): static
    {
        if (!$this->milestones->contains($milestone)) {
            $this->milestones->add($milestone);
            $milestone->setLifeGoal($this);
        }

        return $this;
    }

    public function removeMilestone(LifeMilestone $milestone): static
    {
        if ($this->milestones->removeElement($milestone)) {
            // set the owning side to null (unless already changed)
            if ($milestone->getLifeGoal() === $this) {
                $milestone->setLifeGoal(null);
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
            $habit->setLifeGoal($this);
        }

        return $this;
    }

    public function removeHabit(Habit $habit): static
    {
        if ($this->habits->removeElement($habit)) {
            // set the owning side to null (unless already changed)
            if ($habit->getLifeGoal() === $this) {
                $habit->setLifeGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, GrowthGoal>
     */
    public function getSupportingGrowthGoals(): Collection
    {
        return $this->supportingGrowthGoals;
    }

    public function addSupportingGrowthGoal(GrowthGoal $growthGoal): static
    {
        if (!$this->supportingGrowthGoals->contains($growthGoal)) {
            $this->supportingGrowthGoals->add($growthGoal);
            $growthGoal->addSupportedLifeGoal($this); // Synchronize relation
        }

        return $this;
    }

    public function removeSupportingGrowthGoal(GrowthGoal $growthGoal): static
    {
        if ($this->supportingGrowthGoals->removeElement($growthGoal)) {
            $growthGoal->removeSupportedLifeGoal($this); // Synchronize relation
        }

        return $this;
    }

    /**
     * @return Collection<int, SkillGoal>
     */
    public function getSupportingSkillGoals(): Collection
    {
        return $this->supportingSkillGoals;
    }

    public function addSupportingSkillGoal(SkillGoal $skillGoal): static
    {
        if (!$this->supportingSkillGoals->contains($skillGoal)) {
            $this->supportingSkillGoals->add($skillGoal);
            $skillGoal->addSupportedLifeGoal($this); // Synchronize relation
        }

        return $this;
    }

    public function removeSupportingSkillGoal(SkillGoal $skillGoal): static
    {
        if ($this->supportingSkillGoals->removeElement($skillGoal)) {
            $skillGoal->removeSupportedLifeGoal($this); // Synchronize relation
        }

        return $this;
    }

    /**
     * @return Collection<int, LegacyGoal>
     */
    public function getSupportingLegacyGoals(): Collection
    {
        return $this->supportingLegacyGoals;
    }

    public function addSupportingLegacyGoal(LegacyGoal $legacyGoal): static
    {
        if (!$this->supportingLegacyGoals->contains($legacyGoal)) {
            $this->supportingLegacyGoals->add($legacyGoal);
            $legacyGoal->addSupportedLifeGoal($this);
        }

        return $this;
    }

    public function removeSupportingLegacyGoal(LegacyGoal $legacyGoal): static
    {
        if ($this->supportingLegacyGoals->removeElement($legacyGoal)) {
            $legacyGoal->removeSupportedLifeGoal($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, HygieneDomain>
     */
    public function getSupportingHygieneDomains(): Collection
    {
        return $this->supportingHygieneDomains;
    }

    public function addSupportingHygieneDomain(HygieneDomain $hygieneDomain): static
    {
        if (!$this->supportingHygieneDomains->contains($hygieneDomain)) {
            $this->supportingHygieneDomains->add($hygieneDomain);
            $hygieneDomain->addSupportedLifeGoal($this);
        }

        return $this;
    }

    public function removeSupportingHygieneDomain(HygieneDomain $hygieneDomain): static
    {
        if ($this->supportingHygieneDomains->removeElement($hygieneDomain)) {
            $hygieneDomain->removeSupportedLifeGoal($this);
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
            $reflectionNote->setGoal($this);
        }

        return $this;
    }

    public function removeReflectionNote(ReflectionNote $reflectionNote): static
    {
        if ($this->reflectionNotes->removeElement($reflectionNote)) {
            if ($reflectionNote->getGoal() === $this) {
                $reflectionNote->setGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ListItem>
     */
    public function getListItems(): Collection
    {
        return $this->listItems;
    }

    public function addListItem(ListItem $listItem): static
    {
        if (!$this->listItems->contains($listItem)) {
            $this->listItems->add($listItem);
            $listItem->setGoal($this);
        }

        return $this;
    }

    public function removeListItem(ListItem $listItem): static
    {
        if ($this->listItems->removeElement($listItem)) {
            if ($listItem->getGoal() === $this) {
                $listItem->setGoal(null);
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
            $participant->setLinkedGoal($this);
        }

        return $this;
    }

    public function removeChallengeParticipation(ChallengeParticipant $participant): static
    {
        if ($this->challengeParticipations->removeElement($participant)) {
            if ($participant->getLinkedGoal() === $this) {
                $participant->setLinkedGoal(null);
            }
        }

        return $this;
    }
}
