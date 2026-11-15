<?php

namespace App\PlanDeDeveloppement\Entity;

use App\PlanDeDeveloppement\Enum\SkillCategory;
use App\PlanDeDeveloppement\Repository\SkillGoalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\PlanDeVie\Entity\LifeGoal;
use App\Rituels\Entity\Habit;
use App\Social\Entity\Comment;

#[ORM\Entity(repositoryClass: SkillGoalRepository::class)]
#[ORM\HasLifecycleCallbacks]
class SkillGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'skillGoals')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanDeDeveloppementPersonnel $planDeDeveloppementPersonnel = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 50, type: 'string', enumType: SkillCategory::class)]
    private SkillCategory $category = SkillCategory::TECHNICAL;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetLevel = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column]
    private int $progress = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, LearningResource>
     */
    #[ORM\OneToMany(mappedBy: 'skillGoal', targetEntity: LearningResource::class, cascade: ['persist', 'remove'])]
    private Collection $learningResources;

    /**
     * @var Collection<int, Habit>
     */
    #[ORM\OneToMany(mappedBy: 'skillGoal', targetEntity: Habit::class)]
    private Collection $habits;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'skillGoal', targetEntity: Comment::class, cascade: ['persist', 'remove'])]
    private Collection $comments;

    /**
     * @var Collection<int, LifeGoal>
     */
    #[ORM\ManyToMany(targetEntity: LifeGoal::class, inversedBy: 'supportingSkillGoals')]
    #[ORM\JoinTable(name: 'skill_goal_life_goal')]
    private Collection $supportedLifeGoals;

    public function __construct()
    {
        $this->learningResources = new ArrayCollection();
        $this->habits = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->supportedLifeGoals = new ArrayCollection();
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

    public function getPlanDeDeveloppementPersonnel(): ?PlanDeDeveloppementPersonnel
    {
        return $this->planDeDeveloppementPersonnel;
    }

    public function setPlanDeDeveloppementPersonnel(?PlanDeDeveloppementPersonnel $planDeDeveloppementPersonnel): static
    {
        $this->planDeDeveloppementPersonnel = $planDeDeveloppementPersonnel;

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

    public function getCategory(): SkillCategory
    {
        return $this->category;
    }

    public function setCategory(SkillCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getTargetLevel(): ?string
    {
        return $this->targetLevel;
    }

    public function setTargetLevel(?string $targetLevel): static
    {
        $this->targetLevel = $targetLevel;

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

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;

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
     * @return Collection<int, LearningResource>
     */
    public function getLearningResources(): Collection
    {
        return $this->learningResources;
    }

    public function addLearningResource(LearningResource $learningResource): static
    {
        if (!$this->learningResources->contains($learningResource)) {
            $this->learningResources->add($learningResource);
            $learningResource->setSkillGoal($this);
        }

        return $this;
    }

    public function removeLearningResource(LearningResource $learningResource): static
    {
        if ($this->learningResources->removeElement($learningResource)) {
            // set the owning side to null (unless already changed)
            if ($learningResource->getSkillGoal() === $this) {
                $learningResource->setSkillGoal(null);
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
            $habit->setSkillGoal($this);
        }

        return $this;
    }

    public function removeHabit(Habit $habit): static
    {
        if ($this->habits->removeElement($habit)) {
            // set the owning side to null (unless already changed)
            if ($habit->getSkillGoal() === $this) {
                $habit->setSkillGoal(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setSkillGoal($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getSkillGoal() === $this) {
                $comment->setSkillGoal(null);
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
            $lifeGoal->addSupportingSkillGoal($this);
        }

        return $this;
    }

    public function removeSupportedLifeGoal(LifeGoal $lifeGoal): static
    {
        if ($this->supportedLifeGoals->removeElement($lifeGoal)) {
            $lifeGoal->removeSupportingSkillGoal($this);
        }

        return $this;
    }
}
