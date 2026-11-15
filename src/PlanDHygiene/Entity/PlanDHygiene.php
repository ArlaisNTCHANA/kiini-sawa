<?php

namespace App\PlanDHygiene\Entity;

use App\PlanDHygiene\Repository\PlanDHygieneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;

#[ORM\Entity(repositoryClass: PlanDHygieneRepository::class)]
class PlanDHygiene
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'planDHygiene', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /**
     * @var Collection<int, HygieneDomain>
     */
    #[ORM\OneToMany(mappedBy: 'planDHygiene', targetEntity: HygieneDomain::class, cascade: ['persist', 'remove'])]
    private Collection $hygieneDomains;

    /**
     * @var Collection<int, DailyLog>
     */
    #[ORM\OneToMany(mappedBy: 'planDHygiene', targetEntity: DailyLog::class, cascade: ['persist', 'remove'])]
    private Collection $dailyLogs;

    public function __construct()
    {
        $this->hygieneDomains = new ArrayCollection();
        $this->dailyLogs = new ArrayCollection();
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
     * @return Collection<int, HygieneDomain>
     */
    public function getHygieneDomains(): Collection
    {
        return $this->hygieneDomains;
    }

    public function addHygieneDomain(HygieneDomain $hygieneDomain): static
    {
        if (!$this->hygieneDomains->contains($hygieneDomain)) {
            $this->hygieneDomains->add($hygieneDomain);
            $hygieneDomain->setPlanDHygiene($this);
        }

        return $this;
    }

    public function removeHygieneDomain(HygieneDomain $hygieneDomain): static
    {
        if ($this->hygieneDomains->removeElement($hygieneDomain)) {
            // set the owning side to null (unless already changed)
            if ($hygieneDomain->getPlanDHygiene() === $this) {
                $hygieneDomain->setPlanDHygiene(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, DailyLog>
     */
    public function getDailyLogs(): Collection
    {
        return $this->dailyLogs;
    }

    public function addDailyLog(DailyLog $dailyLog): static
    {
        if (!$this->dailyLogs->contains($dailyLog)) {
            $this->dailyLogs->add($dailyLog);
            $dailyLog->setPlanDHygiene($this);
        }

        return $this;
    }

    public function removeDailyLog(DailyLog $dailyLog): static
    {
        if ($this->dailyLogs->removeElement($dailyLog)) {
            // set the owning side to null (unless already changed)
            if ($dailyLog->getPlanDHygiene() === $this) {
                $dailyLog->setPlanDHygiene(null);
            }
        }

        return $this;
    }
}
