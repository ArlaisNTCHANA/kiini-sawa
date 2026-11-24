<?php

namespace App\Academie\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Module d'un parcours : un regroupement de leçons, avec un titre. */
#[ORM\Entity]
#[ORM\Table(name: 'academy_module')]
class CourseModule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'modules')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column]
    private int $sortOrder = 0;

    /** @var Collection<int, Lesson> */
    #[ORM\OneToMany(targetEntity: Lesson::class, mappedBy: 'module', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $lessons;

    public function __construct()
    {
        $this->lessons = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getCourse(): ?Course { return $this->course; }
    public function setCourse(?Course $course): static { $this->course = $course; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }

    /** @return Collection<int, Lesson> */
    public function getLessons(): Collection { return $this->lessons; }

    public function addLesson(Lesson $lesson): static
    {
        if (!$this->lessons->contains($lesson)) {
            $lesson->setModule($this)->setSortOrder(\count($this->lessons) + 1);
            $this->lessons->add($lesson);
        }

        return $this;
    }

    public function removeLesson(Lesson $lesson): static { $this->lessons->removeElement($lesson); return $this; }
}
