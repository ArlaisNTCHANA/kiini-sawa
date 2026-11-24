<?php

namespace App\Academie\Entity;

use App\Academie\Repository\CourseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Parcours de l'Académie : des modules, eux-mêmes faits de leçons courtes.
 * Un parcours se rattache à un des 6 plans (primaryPlan, clés de PlanCatalog) ou au Sanctuaire du Couple (isCouple),
 * jamais aux deux : le Sanctuaire du Couple n'est pas un 7e plan (docs/lexique.md).
 */
#[ORM\Entity(repositoryClass: CourseRepository::class)]
#[ORM\Table(name: 'academy_course')]
#[ORM\HasLifecycleCallbacks]
class Course
{
    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_PUBLISHED = 'PUBLISHED';

    public const ACCESS_FREE = 'FREE';
    public const ACCESS_PRO = 'PRO';
    public const ACCESS_LEVELS = [self::ACCESS_FREE => 'Gratuit, pour tous les membres', self::ACCESS_PRO => 'Réservé aux abonnés PRO (Duo compris)'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $slug = '';

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $subtitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Un des 6 plans (VIE, CROISSANCE…) ou null (parcours du Sanctuaire du Couple, ou sans plan précis). */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $primaryPlan = null;

    #[ORM\Column]
    private bool $isCouple = false;

    #[ORM\Column(length: 10)]
    private string $accessLevel = self::ACCESS_PRO;

    #[ORM\Column(length: 10)]
    private string $status = self::STATUS_DRAFT;

    #[ORM\Column]
    private int $sortOrder = 0;

    /** Première publication : un parcours déjà publié une fois ne peut plus être supprimé (inscrits possibles). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $firstPublishedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, CourseModule> */
    #[ORM\OneToMany(targetEntity: CourseModule::class, mappedBy: 'course', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $modules;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
        $this->modules = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSlug(): string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getSubtitle(): ?string { return $this->subtitle; }
    public function setSubtitle(?string $subtitle): static { $this->subtitle = $subtitle; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getPrimaryPlan(): ?string { return $this->primaryPlan; }
    public function isCouple(): bool { return $this->isCouple; }

    /** Rattachement : un des 6 plans, 'COUPLE' (Sanctuaire du Couple) ou null. */
    public function setPlace(?string $place): static
    {
        $this->isCouple = 'COUPLE' === $place;
        $this->primaryPlan = $this->isCouple ? null : $place;

        return $this;
    }

    public function getPlace(): ?string { return $this->isCouple ? 'COUPLE' : $this->primaryPlan; }
    public function getAccessLevel(): string { return $this->accessLevel; }
    public function setAccessLevel(string $accessLevel): static { $this->accessLevel = $accessLevel; return $this; }
    public function isFree(): bool { return self::ACCESS_FREE === $this->accessLevel; }
    public function getStatus(): string { return $this->status; }
    public function isPublished(): bool { return self::STATUS_PUBLISHED === $this->status; }

    public function publish(): static
    {
        $this->status = self::STATUS_PUBLISHED;
        $this->firstPublishedAt ??= new \DateTimeImmutable();

        return $this;
    }

    /** Retour en brouillon : le parcours quitte le catalogue ; inscriptions, progressions et notes sont conservées. */
    public function unpublish(): static { $this->status = self::STATUS_DRAFT; return $this; }
    public function wasEverPublished(): bool { return null !== $this->firstPublishedAt; }
    public function getFirstPublishedAt(): ?\DateTimeImmutable { return $this->firstPublishedAt; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    /** @return Collection<int, CourseModule> */
    public function getModules(): Collection { return $this->modules; }

    public function addModule(CourseModule $module): static
    {
        if (!$this->modules->contains($module)) {
            $module->setCourse($this)->setSortOrder(\count($this->modules) + 1);
            $this->modules->add($module);
        }

        return $this;
    }

    public function removeModule(CourseModule $module): static { $this->modules->removeElement($module); return $this; }

    /** @return list<Lesson> toutes les leçons, dans l'ordre des modules. */
    public function getLessons(bool $visibleOnly = false): array
    {
        $out = [];
        foreach ($this->modules as $module) {
            foreach ($module->getLessons() as $lesson) {
                if (!$visibleOnly || $lesson->isVisible()) {
                    $out[] = $lesson;
                }
            }
        }

        return $out;
    }

    /** Durée totale estimée (somme des leçons visibles). */
    public function getEstimatedMinutes(): int
    {
        return array_sum(array_map(fn (Lesson $l) => $l->getDurationMinutes(), $this->getLessons(true)));
    }
}
