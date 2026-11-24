<?php

namespace App\Academie\Entity;

use App\Academie\Repository\LessonNoteRepository;
use App\Journal\Entity\ReflectionNote;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien entre une note du Journal (carnet « Académie ») et la leçon où elle a été écrite, avec le plan auquel elle se
 * rapporte. La note elle-même vit dans le Journal (décision du fondateur, 24/11/2026) : la supprimer là-bas supprime
 * ce lien ; supprimer une leçon garde la note.
 */
#[ORM\Entity(repositoryClass: LessonNoteRepository::class)]
#[ORM\Table(name: 'academy_lesson_note')]
class LessonNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ReflectionNote $note;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lesson $lesson;

    /** Un des 6 plans (clé de PlanCatalog), ou null. */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $planKey;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(ReflectionNote $note, ?Lesson $lesson, ?string $planKey)
    {
        $this->note = $note;
        $this->lesson = $lesson;
        $this->planKey = $planKey;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getNote(): ReflectionNote { return $this->note; }
    public function getLesson(): ?Lesson { return $this->lesson; }
    public function getPlanKey(): ?string { return $this->planKey; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
