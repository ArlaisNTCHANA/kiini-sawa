<?php

namespace App\Academie\Entity;

use App\Academie\Repository\CourseEnrollmentRepository;
use App\Compte\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Inscription d'un membre à un parcours : créée à la première leçon ouverte. Elle garde l'accès au parcours même s'il
 * est repassé en brouillon (décision du fondateur, 24/11/2026), et porte la progression (leçons terminées).
 */
#[ORM\Entity(repositoryClass: CourseEnrollmentRepository::class)]
#[ORM\Table(name: 'academy_enrollment')]
#[ORM\UniqueConstraint(name: 'uniq_academy_enrollment', columns: ['user_id', 'course_id'])]
class CourseEnrollment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Course $course;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastActivityAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    /** @var Collection<int, LessonCompletion> */
    #[ORM\OneToMany(targetEntity: LessonCompletion::class, mappedBy: 'enrollment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $completions;

    public function __construct(User $user, Course $course)
    {
        $this->user = $user;
        $this->course = $course;
        $this->startedAt = new \DateTimeImmutable();
        $this->lastActivityAt = $this->startedAt;
        $this->completions = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getCourse(): Course { return $this->course; }
    public function getStartedAt(): \DateTimeImmutable { return $this->startedAt; }
    public function getLastActivityAt(): \DateTimeImmutable { return $this->lastActivityAt; }
    public function touch(): static { $this->lastActivityAt = new \DateTimeImmutable(); return $this; }
    public function getCompletedAt(): ?\DateTimeImmutable { return $this->completedAt; }
    public function isCompleted(): bool { return null !== $this->completedAt; }

    /** @return Collection<int, LessonCompletion> */
    public function getCompletions(): Collection { return $this->completions; }

    public function completionFor(Lesson $lesson): ?LessonCompletion
    {
        foreach ($this->completions as $c) {
            if ($c->getLesson() === $lesson) {
                return $c;
            }
        }

        return null;
    }

    public function hasCompleted(Lesson $lesson): bool
    {
        return null !== $this->completionFor($lesson);
    }

    /** Termine une leçon (une seule ligne par leçon) ; le parcours est terminé quand toutes ses leçons visibles le sont. */
    public function complete(Lesson $lesson): LessonCompletion
    {
        $completion = $this->completionFor($lesson);
        if (null === $completion) {
            $completion = new LessonCompletion($this, $lesson);
            $this->completions->add($completion);
        }
        $this->touch();
        if (null === $this->completedAt && null === $this->nextLesson()) {
            $this->completedAt = new \DateTimeImmutable();
        }

        return $completion;
    }

    /** Première leçon visible pas encore terminée, ou null si tout est fait. */
    public function nextLesson(): ?Lesson
    {
        foreach ($this->course->getLessons(true) as $lesson) {
            if (!$this->hasCompleted($lesson)) {
                return $lesson;
            }
        }

        return null;
    }

    /** @return array{done: int, total: int, percent: int} progression sur les leçons visibles */
    public function progress(): array
    {
        $lessons = $this->course->getLessons(true);
        $done = \count(array_filter($lessons, fn (Lesson $l) => $this->hasCompleted($l)));
        $total = \count($lessons);

        return ['done' => $done, 'total' => $total, 'percent' => $total ? (int) round(100 * $done / $total) : 0];
    }
}
