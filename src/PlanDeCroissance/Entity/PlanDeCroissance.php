<?php

namespace App\PlanDeCroissance\Entity;

use App\PlanDeCroissance\Repository\PlanDeCroissanceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: PlanDeCroissanceRepository::class)]
class PlanDeCroissance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'planDeCroissance', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var Collection<int, GrowthGoal>
     */
    #[ORM\OneToMany(mappedBy: 'planDeCroissance', targetEntity: GrowthGoal::class, cascade: ['persist', 'remove'])]
    private Collection $growthGoals;

    /**
     * @var Collection<int, ConfirmPoint>
     */
    #[ORM\OneToMany(mappedBy: 'planDeCroissance', targetEntity: ConfirmPoint::class, cascade: ['persist', 'remove'])]
    private Collection $confirmPoints;

    public function __construct()
    {
        $this->growthGoals = new ArrayCollection();
        $this->confirmPoints = new ArrayCollection();
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
     * @return Collection<int, GrowthGoal>
     */
    public function getGrowthGoals(): Collection
    {
        return $this->growthGoals;
    }

    public function addGrowthGoal(GrowthGoal $growthGoal): static
    {
        if (!$this->growthGoals->contains($growthGoal)) {
            $this->growthGoals->add($growthGoal);
            $growthGoal->setPlanDeCroissance($this);
        }

        return $this;
    }

    public function removeGrowthGoal(GrowthGoal $growthGoal): static
    {
        if ($this->growthGoals->removeElement($growthGoal)) {
            // set the owning side to null (unless already changed)
            if ($growthGoal->getPlanDeCroissance() === $this) {
                $growthGoal->setPlanDeCroissance(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ConfirmPoint>
     */
    public function getConfirmPoints(): Collection
    {
        return $this->confirmPoints;
    }

    public function addConfirmPoint(ConfirmPoint $confirmPoint): static
    {
        if (!$this->confirmPoints->contains($confirmPoint)) {
            $this->confirmPoints->add($confirmPoint);
            $confirmPoint->setPlanDeCroissance($this);
        }

        return $this;
    }

    public function removeConfirmPoint(ConfirmPoint $confirmPoint): static
    {
        if ($this->confirmPoints->removeElement($confirmPoint)) {
            // set the owning side to null (unless already changed)
            if ($confirmPoint->getPlanDeCroissance() === $this) {
                $confirmPoint->setPlanDeCroissance(null);
            }
        }

        return $this;
    }
}
