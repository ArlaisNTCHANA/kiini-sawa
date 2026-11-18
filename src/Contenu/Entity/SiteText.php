<?php

namespace App\Contenu\Entity;

use App\Compte\Entity\User;
use App\Contenu\Repository\SiteTextRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un texte du site modifié dans l'administration (vitrine, pages légales, emails, méthode…).
 * Le code (ou le fichier `content/textes/`) reste le texte d'origine ; une ligne ici ne porte que les modifications :
 *  - « published » : le texte en ligne (null = texte d'origine) ;
 *  - « draft » : un brouillon en cours de relecture (null = pas de brouillon), visible seulement en mode aperçu.
 */
#[ORM\Entity(repositoryClass: SiteTextRepository::class)]
#[ORM\Table(name: 'site_text')]
class SiteText
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant stable du texte, ex. « vitrine.hero.titre » ou « legal.cgu ». */
    #[ORM\Column(length: 190, unique: true)]
    private string $code;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $published = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $draft = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $draftAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function setDraft(string $content, ?User $by): void
    {
        $this->draft = $content;
        $this->draftAt = new \DateTimeImmutable();
        $this->updatedBy = $by;
    }

    public function discardDraft(): void
    {
        $this->draft = null;
        $this->draftAt = null;
    }

    /** Met en ligne le texte donné (null : retour au texte d'origine) et vide le brouillon. */
    public function publish(?string $content, ?User $by): void
    {
        $this->published = $content;
        $this->publishedAt = new \DateTimeImmutable();
        $this->updatedBy = $by;
        $this->discardDraft();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getPublished(): ?string { return $this->published; }
    public function getDraft(): ?string { return $this->draft; }
    public function getPublishedAt(): ?\DateTimeImmutable { return $this->publishedAt; }
    public function getDraftAt(): ?\DateTimeImmutable { return $this->draftAt; }
    public function getUpdatedBy(): ?User { return $this->updatedBy; }
}
