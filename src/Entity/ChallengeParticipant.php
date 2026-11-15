<?php

namespace App\Entity;

use App\Repository\ChallengeParticipantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChallengeParticipantRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CHALLENGE_USER', fields: ['challenge', 'user'])]
class ChallengeParticipant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Challenge $challenge = null;

    #[ORM\ManyToOne(inversedBy: 'challenges')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\Column(length: 50)]
    private string $status = 'ACTIVE';

    #[ORM\Column]
    private int $progress = 0;

    #[ORM\ManyToOne(inversedBy: 'challengeParticipations')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?LifeGoal $linkedGoal = null;

    #[ORM\ManyToOne(inversedBy: 'challengeParticipations')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GrowthGoal $linkedGrowthGoal = null;

    #[ORM\OneToMany(mappedBy: 'participant', targetEntity: ChallengeLog::class, cascade: ['persist', 'remove'])]
    private Collection $logs;

    public function __construct()
    {
        $this->joinedAt = new \DateTime();
        $this->logs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChallenge(): ?Challenge
    {
        return $this->challenge;
    }

    public function setChallenge(?Challenge $challenge): static
    {
        $this->challenge = $challenge;

        return $this;
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

    public function getJoinedAt(): ?\DateTimeInterface
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeInterface $joinedAt): static
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    public function getLinkedGoal(): ?LifeGoal
    {
        return $this->linkedGoal;
    }

    public function setLinkedGoal(?LifeGoal $linkedGoal): static
    {
        $this->linkedGoal = $linkedGoal;

        return $this;
    }

    public function getLinkedGrowthGoal(): ?GrowthGoal
    {
        return $this->linkedGrowthGoal;
    }

    public function setLinkedGrowthGoal(?GrowthGoal $linkedGrowthGoal): static
    {
        $this->linkedGrowthGoal = $linkedGrowthGoal;

        return $this;
    }

    /**
     * @return Collection<int, ChallengeLog>
     */
    public function getLogs(): Collection
    {
        return $this->logs;
    }

    public function addLog(ChallengeLog $log): static
    {
        if (!$this->logs->contains($log)) {
            $this->logs->add($log);
            $log->setParticipant($this);
        }

        return $this;
    }

    public function removeLog(ChallengeLog $log): static
    {
        if ($this->logs->removeElement($log)) {
            if ($log->getParticipant() === $this) {
                $log->setParticipant(null);
            }
        }

        return $this;
    }
}
