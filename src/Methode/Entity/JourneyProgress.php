<?php

namespace App\Methode\Entity;

use App\Compte\Entity\User;
use App\Methode\Repository\JourneyProgressRepository;
use App\Methode\Service\Journey;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Avancement d'un utilisateur dans le parcours de 90 jours (livre, annexe A).
 */
#[ORM\Entity(repositoryClass: JourneyProgressRepository::class)]
class JourneyProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    /** Semaines terminées (numéros 1 à 13). */
    #[ORM\Column(type: Types::JSON)]
    private array $completedWeeks = [];

    public function __construct()
    {
        $this->startedAt = new \DateTimeImmutable('today');
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }

    public function setStartedAt(\DateTimeImmutable $startedAt): static { $this->startedAt = $startedAt; return $this; }

    /** @return list<int> */
    public function getCompletedWeeks(): array { return $this->completedWeeks; }

    public function isWeekDone(int $week): bool { return \in_array($week, $this->completedWeeks, true); }

    public function toggleWeek(int $week): static
    {
        if ($week >= 1 && $week <= Journey::WEEKS) {
            $this->completedWeeks = $this->isWeekDone($week)
                ? array_values(array_diff($this->completedWeeks, [$week]))
                : array_values(array_unique([...$this->completedWeeks, $week]));
            sort($this->completedWeeks);
        }

        return $this;
    }

    public function currentWeek(): int { return Journey::currentWeek($this->startedAt); }

    public function isFinished(): bool { return \count($this->completedWeeks) >= Journey::WEEKS; }
}
