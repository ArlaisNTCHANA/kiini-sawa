<?php

namespace App\PlanDeTransmission\Entity;

use App\PlanDeTransmission\Repository\LegacyGoalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\PlanDeVie\Entity\LifeGoal;
use App\Rituels\Entity\Habit;
use App\Social\Entity\Comment;

#[ORM\Entity(repositoryClass: LegacyGoalRepository::class)]
#[ORM\HasLifecycleCallbacks]
class LegacyGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'legacyGoals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanDeTransmission $planDeTransmission = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetBeneficiary = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $progress = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deadline = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $subtype = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, TransmissionAction>
     */
    #[ORM\OneToMany(mappedBy: 'legacyGoal', targetEntity: TransmissionAction::class, cascade: ['persist', 'remove'])]
    private Collection $transmissionActions;

    /**
     * @var Collection<int, Habit>
     */
    #[ORM\OneToMany(mappedBy: 'legacyGoal', targetEntity: Habit::class)]
    private Collection $habits;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'legacyGoal', targetEntity: Comment::class, cascade: ['persist', 'remove'])]
    private Collection $comments;

    /**
     * @var Collection<int, LifeGoal>
     */
    #[ORM\ManyToMany(targetEntity: LifeGoal::class, inversedBy: 'supportingLegacyGoals')]
    #[ORM\JoinTable(name: 'legacy_goal_life_goal')]
    private Collection $supportedLifeGoals;

    public function __construct()
    {
        $this->transmissionActions = new ArrayCollection();
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

    public function getPlanDeTransmission(): ?PlanDeTransmission
    {
        return $this->planDeTransmission;
    }

    public function setPlanDeTransmission(?PlanDeTransmission $planDeTransmission): static
    {
        $this->planDeTransmission = $planDeTransmission;

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

    public function getTargetBeneficiary(): ?string
    {
        return $this->targetBeneficiary;
    }

    public function setTargetBeneficiary(?string $targetBeneficiary): static
    {
        $this->targetBeneficiary = $targetBeneficiary;

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

    public function getProgress(): int
    {
        return $this->progress;
    }

    public function setProgress(int $progress): static
    {
        $this->progress = $progress;

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
     * @return Collection<int, TransmissionAction>
     */
    public function getTransmissionActions(): Collection
    {
        return $this->transmissionActions;
    }

    public function addTransmissionAction(TransmissionAction $transmissionAction): static
    {
        if (!$this->transmissionActions->contains($transmissionAction)) {
            $this->transmissionActions->add($transmissionAction);
            $transmissionAction->setLegacyGoal($this);
        }

        return $this;
    }

    public function removeTransmissionAction(TransmissionAction $transmissionAction): static
    {
        if ($this->transmissionActions->removeElement($transmissionAction)) {
            // set the owning side to null (unless already changed)
            if ($transmissionAction->getLegacyGoal() === $this) {
                $transmissionAction->setLegacyGoal(null);
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
            $habit->setLegacyGoal($this);
        }

        return $this;
    }

    public function removeHabit(Habit $habit): static
    {
        if ($this->habits->removeElement($habit)) {
            // set the owning side to null (unless already changed)
            if ($habit->getLegacyGoal() === $this) {
                $habit->setLegacyGoal(null);
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
            $comment->setLegacyGoal($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getLegacyGoal() === $this) {
                $comment->setLegacyGoal(null);
            }
        }

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
        }

        return $this;
    }

    public function removeSupportedLifeGoal(LifeGoal $lifeGoal): static
    {
        $this->supportedLifeGoals->removeElement($lifeGoal);

        return $this;
    }
}
