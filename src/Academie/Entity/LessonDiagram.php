<?php

namespace App\Academie\Entity;

use App\Academie\Repository\LessonDiagramRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Schéma créé dans l'atelier du studio (sans code) : un modèle (DiagramModels::MODELS) et son contenu.
 * Placé dans une leçon par `[[schema:<code>]]`, comme les schémas dessinés dans le code (LessonRenderer::DIAGRAMS).
 * Le code est fixé à la création : renommer le schéma ne casse pas les leçons qui l'utilisent.
 */
#[ORM\Entity(repositoryClass: LessonDiagramRepository::class)]
#[ORM\Table(name: 'academy_diagram')]
#[ORM\HasLifecycleCallbacks]
class LessonDiagram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 120)]
    private string $title = '';

    #[ORM\Column(length: 20)]
    private string $model = 'cards';

    /** @var array<string, mixed> contenu normalisé par DiagramModels::normalize */
    #[ORM\Column(type: Types::JSON)]
    private array $data = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function setCode(string $code): static { $this->code = $code; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getModel(): string { return $this->model; }
    public function setModel(string $model): static { $this->model = $model; return $this; }
    /** @return array<string, mixed> */
    public function getData(): array { return $this->data; }
    /** @param array<string, mixed> $data */
    public function setData(array $data): static { $this->data = $data; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function token(): string
    {
        return '[[schema:'.$this->code.']]';
    }
}
