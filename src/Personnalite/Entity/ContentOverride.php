<?php

namespace App\Personnalite\Entity;

use App\Compte\Entity\User;
use App\Personnalite\Repository\ContentOverrideRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Texte d'un test modifié par l'administrateur (libellé de question, texte de rapport…).
 * Le code reste la version de référence : supprimer la ligne revient au texte d'origine.
 */
#[ORM\Entity(repositoryClass: ContentOverrideRepository::class)]
#[ORM\Table(name: 'content_override')]
class ContentOverride
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Chemin du texte, ex. « disc.profiles.SC.portrait ». */
    #[ORM\Column(length: 190, unique: true)]
    private string $path;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct(string $path, string $content, ?User $by)
    {
        $this->path = $path;
        $this->update($content, $by);
    }

    public function update(string $content, ?User $by): void
    {
        $this->content = $content;
        $this->updatedBy = $by;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getPath(): string { return $this->path; }
    public function getContent(): string { return $this->content; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function getUpdatedBy(): ?User { return $this->updatedBy; }
}
