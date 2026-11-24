<?php

namespace App\Academie\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Leçon de 5 à 10 minutes : texte (Markdown, rendu en mode sûr), vidéo facultative (YouTube ou Vimeo, identifiant
 * seulement : l'adresse de lecture est reconstruite par VideoEmbed), questions de discernement et exercice relié à un plan.
 */
#[ORM\Entity]
#[ORM\Table(name: 'academy_lesson')]
#[ORM\UniqueConstraint(name: 'uniq_academy_lesson_module_slug', fields: ['module', 'slug'])]
#[ORM\HasLifecycleCallbacks]
class Lesson
{
    public const MEDIA_NONE = 'NONE';
    public const MEDIA_YOUTUBE = 'YOUTUBE';
    public const MEDIA_VIMEO = 'VIMEO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lessons')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CourseModule $module = null;

    #[ORM\Column(length: 100)]
    private string $slug = '';

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column]
    private int $sortOrder = 0;

    /** Visible des apprenants. Une leçon ajoutée à un parcours déjà publié reste masquée tant qu'elle n'est pas prête. */
    #[ORM\Column]
    private bool $visible = true;

    #[ORM\Column(type: Types::TEXT)]
    private string $contentMarkdown = '';

    #[ORM\Column(length: 10)]
    private string $mediaProvider = self::MEDIA_NONE;

    /** Identifiant de la vidéo (YouTube : 11 caractères ; Vimeo : chiffres, avec « /clé » pour une vidéo non répertoriée). */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $mediaRef = null;

    #[ORM\Column]
    private int $durationMinutes = 5;

    /** Consigne de l'exercice de fin de leçon. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $practicePrompt = null;

    /** Où va l'exercice : clé de PracticeCatalog::TARGETS (un des 6 plans, RITUEL, JOURNAL, COUPLE). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $targetPlan = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $targetAction = null;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, LessonQuestion> */
    #[ORM\OneToMany(targetEntity: LessonQuestion::class, mappedBy: 'lesson', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $questions;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getModule(): ?CourseModule { return $this->module; }
    public function setModule(?CourseModule $module): static { $this->module = $module; return $this; }
    public function getCourse(): ?Course { return $this->module?->getCourse(); }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }
    public function isVisible(): bool { return $this->visible; }
    public function setVisible(bool $visible): static { $this->visible = $visible; return $this; }
    public function getContentMarkdown(): string { return $this->contentMarkdown; }
    public function setContentMarkdown(string $contentMarkdown): static { $this->contentMarkdown = $contentMarkdown; return $this; }
    public function getMediaProvider(): string { return $this->mediaProvider; }
    public function getMediaRef(): ?string { return $this->mediaRef; }
    public function hasVideo(): bool { return self::MEDIA_NONE !== $this->mediaProvider && null !== $this->mediaRef; }

    public function setVideo(?string $provider, ?string $ref): static
    {
        $this->mediaProvider = $provider && $ref ? $provider : self::MEDIA_NONE;
        $this->mediaRef = $provider && $ref ? $ref : null;

        return $this;
    }

    public function getDurationMinutes(): int { return $this->durationMinutes; }
    public function setDurationMinutes(int $durationMinutes): static { $this->durationMinutes = $durationMinutes; return $this; }
    public function getPracticePrompt(): ?string { return $this->practicePrompt; }
    public function setPracticePrompt(?string $practicePrompt): static { $this->practicePrompt = $practicePrompt; return $this; }
    public function getTargetPlan(): ?string { return $this->targetPlan; }
    public function getTargetAction(): ?string { return $this->targetAction; }

    public function setPractice(?string $target, ?string $action): static
    {
        $this->targetPlan = $target && $action ? $target : null;
        $this->targetAction = $target && $action ? $action : null;

        return $this;
    }

    public function hasPractice(): bool { return null !== $this->targetAction; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, LessonQuestion> */
    public function getQuestions(): Collection { return $this->questions; }

    public function addQuestion(LessonQuestion $question): static
    {
        if (!$this->questions->contains($question)) {
            $question->setLesson($this)->setSortOrder(\count($this->questions) + 1);
            $this->questions->add($question);
        }

        return $this;
    }

    public function removeQuestion(LessonQuestion $question): static { $this->questions->removeElement($question); return $this; }
}
