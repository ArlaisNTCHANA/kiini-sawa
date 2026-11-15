<?php

namespace App\Personnalite\Entity;

use App\Compte\Entity\User;
use App\Personnalite\Repository\AssessmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Passation d'un questionnaire : réponses item par item, scores calculés, qualité des réponses.
 * Les réponses brutes sont conservées pour recalculer les scores et mesurer la fidélité des échelles.
 */
#[ORM\Entity(repositoryClass: AssessmentRepository::class)]
#[ORM\Index(name: 'idx_assessment_instrument', columns: ['instrument'])]
class Assessment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 30)]
    private string $instrument;

    /** @var array<string, int> */
    #[ORM\Column(type: Types::JSON)]
    private array $answers;

    #[ORM\Column(type: Types::JSON)]
    private array $scores = [];

    #[ORM\Column(type: Types::JSON)]
    private array $quality = [];

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds;

    #[ORM\Column]
    private \DateTimeImmutable $takenAt;

    public function __construct(User $user, string $instrument, array $answers, ?int $durationSeconds)
    {
        $this->user = $user;
        $this->instrument = $instrument;
        $this->answers = $answers;
        $this->durationSeconds = $durationSeconds;
        $this->takenAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getInstrument(): string { return $this->instrument; }
    public function getAnswers(): array { return $this->answers; }
    public function getScores(): array { return $this->scores; }
    public function setScores(array $scores): static { $this->scores = $scores; return $this; }
    public function getQuality(): array { return $this->quality; }
    public function setQuality(array $quality): static { $this->quality = $quality; return $this; }
    public function getDurationSeconds(): ?int { return $this->durationSeconds; }
    public function getTakenAt(): \DateTimeImmutable { return $this->takenAt; }
}
