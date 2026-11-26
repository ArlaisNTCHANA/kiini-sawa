<?php

namespace App\Academie\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Leçon terminée par un membre, avec sa réponse à « Mettre en pratique » et, s'il a accepté de l'enregistrer, l'élément
 * créé dans un de ses plans (type + identifiant). Refaire l'exercice met à jour cet élément, sans doublon.
 */
#[ORM\Entity]
#[ORM\Table(name: 'academy_completion')]
#[ORM\UniqueConstraint(name: 'uniq_academy_completion', columns: ['enrollment_id', 'lesson_id'])]
class LessonCompletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'completions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CourseEnrollment $enrollment;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Lesson $lesson;

    /** @var array{target?: string, action?: string, title?: string, detail?: ?string, choice?: ?string}|null */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $response = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $createdEntityType = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdEntityId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $completedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(CourseEnrollment $enrollment, Lesson $lesson)
    {
        $this->enrollment = $enrollment;
        $this->lesson = $lesson;
        $this->completedAt = new \DateTimeImmutable();
        $this->updatedAt = $this->completedAt;
    }

    public function getId(): ?int { return $this->id; }
    public function getEnrollment(): CourseEnrollment { return $this->enrollment; }
    public function getLesson(): Lesson { return $this->lesson; }
    public function getResponse(): ?array { return $this->response; }
    public function getCreatedEntityType(): ?string { return $this->createdEntityType; }
    public function getCreatedEntityId(): ?int { return $this->createdEntityId; }
    public function hasCreatedEntity(): bool { return null !== $this->createdEntityId; }
    public function getCompletedAt(): \DateTimeImmutable { return $this->completedAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function recordPractice(array $response, ?string $entityType, ?int $entityId): static
    {
        $this->response = $response;
        $this->createdEntityType = $entityType;
        $this->createdEntityId = $entityId;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
