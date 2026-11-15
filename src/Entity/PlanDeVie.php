<?php

namespace App\Entity;

use App\Repository\PlanDeVieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanDeVieRepository::class)]
class PlanDeVie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'planDeVie', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToOne(mappedBy: 'planDeVie', cascade: ['persist', 'remove'])]
    private ?Purpose $purpose = null;

    #[ORM\OneToOne(mappedBy: 'planDeVie', cascade: ['persist', 'remove'])]
    private ?Vision $vision = null;

    /**
     * @var Collection<int, Value>
     */
    #[ORM\OneToMany(mappedBy: 'planDeVie', targetEntity: Value::class, cascade: ['persist', 'remove'])]
    private Collection $values;

    /**
     * @var Collection<int, Passion>
     */
    #[ORM\OneToMany(mappedBy: 'planDeVie', targetEntity: Passion::class, cascade: ['persist', 'remove'])]
    private Collection $passions;

    /**
     * @var Collection<int, LifeGoal>
     */
    #[ORM\OneToMany(mappedBy: 'planDeVie', targetEntity: LifeGoal::class, cascade: ['persist', 'remove'])]
    private Collection $lifeGoals;

    public function __construct()
    {
        $this->values = new ArrayCollection();
        $this->passions = new ArrayCollection();
        $this->lifeGoals = new ArrayCollection();
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

    public function getPurpose(): ?Purpose
    {
        return $this->purpose;
    }

    public function setPurpose(?Purpose $purpose): static
    {
        // set the owning side of the relation if necessary
        if ($purpose !== null && $purpose->getPlanDeVie() !== $this) {
            $purpose->setPlanDeVie($this);
        }

        $this->purpose = $purpose;

        return $this;
    }

    public function getVision(): ?Vision
    {
        return $this->vision;
    }

    public function setVision(?Vision $vision): static
    {
        // set the owning side of the relation if necessary
        if ($vision !== null && $vision->getPlanDeVie() !== $this) {
            $vision->setPlanDeVie($this);
        }

        $this->vision = $vision;

        return $this;
    }

    /**
     * @return Collection<int, Value>
     */
    public function getValues(): Collection
    {
        return $this->values;
    }

    public function addValue(Value $value): static
    {
        if (!$this->values->contains($value)) {
            $this->values->add($value);
            $value->setPlanDeVie($this);
        }

        return $this;
    }

    public function removeValue(Value $value): static
    {
        if ($this->values->removeElement($value)) {
            // set the owning side to null (unless already changed)
            if ($value->getPlanDeVie() === $this) {
                $value->setPlanDeVie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Passion>
     */
    public function getPassions(): Collection
    {
        return $this->passions;
    }

    public function addPassion(Passion $passion): static
    {
        if (!$this->passions->contains($passion)) {
            $this->passions->add($passion);
            $passion->setPlanDeVie($this);
        }

        return $this;
    }

    public function removePassion(Passion $passion): static
    {
        if ($this->passions->removeElement($passion)) {
            // set the owning side to null (unless already changed)
            if ($passion->getPlanDeVie() === $this) {
                $passion->setPlanDeVie(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LifeGoal>
     */
    public function getLifeGoals(): Collection
    {
        return $this->lifeGoals;
    }

    public function addLifeGoal(LifeGoal $lifeGoal): static
    {
        if (!$this->lifeGoals->contains($lifeGoal)) {
            $this->lifeGoals->add($lifeGoal);
            $lifeGoal->setPlanDeVie($this);
        }

        return $this;
    }

    public function removeLifeGoal(LifeGoal $lifeGoal): static
    {
        if ($this->lifeGoals->removeElement($lifeGoal)) {
            // set the owning side to null (unless already changed)
            if ($lifeGoal->getPlanDeVie() === $this) {
                $lifeGoal->setPlanDeVie(null);
            }
        }

        return $this;
    }
}

