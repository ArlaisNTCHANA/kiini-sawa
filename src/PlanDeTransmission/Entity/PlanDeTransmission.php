<?php

namespace App\PlanDeTransmission\Entity;

use App\PlanDeTransmission\Repository\PlanDeTransmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: PlanDeTransmissionRepository::class)]
class PlanDeTransmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'planDeTransmission', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var Collection<int, LegacyGoal>
     */
    #[ORM\OneToMany(mappedBy: 'planDeTransmission', targetEntity: LegacyGoal::class, cascade: ['persist', 'remove'])]
    private Collection $legacyGoals;

    /**
     * @var Collection<int, TalentDon>
     */
    #[ORM\OneToMany(mappedBy: 'planDeTransmission', targetEntity: TalentDon::class, cascade: ['persist', 'remove'])]
    private Collection $talentDons;

    public function __construct()
    {
        $this->legacyGoals = new ArrayCollection();
        $this->talentDons = new ArrayCollection();
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
     * @return Collection<int, LegacyGoal>
     */
    public function getLegacyGoals(): Collection
    {
        return $this->legacyGoals;
    }

    public function addLegacyGoal(LegacyGoal $legacyGoal): static
    {
        if (!$this->legacyGoals->contains($legacyGoal)) {
            $this->legacyGoals->add($legacyGoal);
            $legacyGoal->setPlanDeTransmission($this);
        }

        return $this;
    }

    public function removeLegacyGoal(LegacyGoal $legacyGoal): static
    {
        if ($this->legacyGoals->removeElement($legacyGoal)) {
            // set the owning side to null (unless already changed)
            if ($legacyGoal->getPlanDeTransmission() === $this) {
                $legacyGoal->setPlanDeTransmission(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, TalentDon>
     */
    public function getTalentDons(): Collection
    {
        return $this->talentDons;
    }

    public function addTalentDon(TalentDon $talentDon): static
    {
        if (!$this->talentDons->contains($talentDon)) {
            $this->talentDons->add($talentDon);
            $talentDon->setPlanDeTransmission($this);
        }

        return $this;
    }

    public function removeTalentDon(TalentDon $talentDon): static
    {
        if ($this->talentDons->removeElement($talentDon)) {
            // set the owning side to null (unless already changed)
            if ($talentDon->getPlanDeTransmission() === $this) {
                $talentDon->setPlanDeTransmission(null);
            }
        }

        return $this;
    }
}
