<?php

namespace App\Bilan\Entity;

use App\Bilan\Repository\WeeklyReviewRepository;
use App\Compte\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Revue hebdomadaire : une par utilisateur et par semaine (lundi).
 * Les évaluations portent sur les éléments des quatre plans stratégiques ;
 * elles sont conservées telles qu'à la date de la revue (titres compris).
 */
#[ORM\Entity(repositoryClass: WeeklyReviewRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_weekly_review_user_week', columns: ['user_id', 'week_start'])]
class WeeklyReview
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $weekStart;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $wins = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $struggles = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lesson = null;

    /** @var list<string> trois priorités de la semaine suivante */
    #[ORM\Column(type: Types::JSON)]
    private array $priorities = [];

    /** @var list<array{plan: string, type: string, id: int, title: string, score: ?int, progress: ?int, note: ?string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $items = [];

    #[ORM\Column]
    private \DateTimeImmutable $completedAt;

    public function __construct(User $user, \DateTimeImmutable $weekStart)
    {
        $this->user = $user;
        $this->weekStart = $weekStart;
        $this->completedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getWeekStart(): \DateTimeImmutable { return $this->weekStart; }
    public function getWins(): ?string { return $this->wins; }
    public function setWins(?string $wins): static { $this->wins = $wins; return $this; }
    public function getStruggles(): ?string { return $this->struggles; }
    public function setStruggles(?string $struggles): static { $this->struggles = $struggles; return $this; }
    public function getLesson(): ?string { return $this->lesson; }
    public function setLesson(?string $lesson): static { $this->lesson = $lesson; return $this; }
    public function getPriorities(): array { return $this->priorities; }
    public function setPriorities(array $priorities): static { $this->priorities = array_values($priorities); return $this; }
    public function getItems(): array { return $this->items; }
    public function setItems(array $items): static { $this->items = array_values($items); return $this; }
    public function getCompletedAt(): \DateTimeImmutable { return $this->completedAt; }
    public function touch(): static { $this->completedAt = new \DateTimeImmutable(); return $this; }

    /** Moyenne des évaluations (sur 10), ou null. */
    public function getAverageScore(): ?float
    {
        $scores = array_filter(array_column($this->items, 'score'), fn ($s) => null !== $s);

        return [] === $scores ? null : round(array_sum($scores) / \count($scores), 1);
    }
}
