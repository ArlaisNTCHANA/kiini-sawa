<?php

namespace App\PlanDeDeveloppement\Entity;

use App\PlanDeDeveloppement\Repository\PlanDeDeveloppementPersonnelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: PlanDeDeveloppementPersonnelRepository::class)]
class PlanDeDeveloppementPersonnel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'planDeDeveloppementPersonnel', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var Collection<int, SkillGoal>
     */
    #[ORM\OneToMany(mappedBy: 'planDeDeveloppementPersonnel', targetEntity: SkillGoal::class, cascade: ['persist', 'remove'])]
    private Collection $skillGoals;

    /**
     * @var Collection<int, CareerStep>
     */
    #[ORM\OneToMany(mappedBy: 'planDeDeveloppementPersonnel', targetEntity: CareerStep::class, cascade: ['persist', 'remove'])]
    private Collection $careerSteps;

    public function __construct()
    {
        $this->skillGoals = new ArrayCollection();
        $this->careerSteps = new ArrayCollection();
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

    /**
     * @return Collection<int, SkillGoal>
     */
    public function getSkillGoals(): Collection
    {
        return $this->skillGoals;
    }

    public function addSkillGoal(SkillGoal $skillGoal): static
    {
        if (!$this->skillGoals->contains($skillGoal)) {
            $this->skillGoals->add($skillGoal);
            $skillGoal->setPlanDeDeveloppementPersonnel($this);
        }

        return $this;
    }

    public function removeSkillGoal(SkillGoal $skillGoal): static
    {
        if ($this->skillGoals->removeElement($skillGoal)) {
            // set the owning side to null (unless already changed)
            if ($skillGoal->getPlanDeDeveloppementPersonnel() === $this) {
                $skillGoal->setPlanDeDeveloppementPersonnel(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, CareerStep>
     */
    public function getCareerSteps(): Collection
    {
        return $this->careerSteps;
    }

    public function addCareerStep(CareerStep $careerStep): static
    {
        if (!$this->careerSteps->contains($careerStep)) {
            $this->careerSteps->add($careerStep);
            $careerStep->setPlanDeDeveloppementPersonnel($this);
        }

        return $this;
    }

    public function removeCareerStep(CareerStep $careerStep): static
    {
        if ($this->careerSteps->removeElement($careerStep)) {
            // set the owning side to null (unless already changed)
            if ($careerStep->getPlanDeDeveloppementPersonnel() === $this) {
                $careerStep->setPlanDeDeveloppementPersonnel(null);
            }
        }

        return $this;
    }
}
