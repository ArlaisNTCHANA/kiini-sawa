<?php

namespace App\Contenu\Entity;

use App\Compte\Entity\User;
use App\Contenu\Repository\SiteTextVersionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Historique : une ligne à chaque mise en ligne ou retour au texte d'origine, pour pouvoir revenir en arrière. */
#[ORM\Entity(repositoryClass: SiteTextVersionRepository::class)]
#[ORM\Table(name: 'site_text_version')]
#[ORM\Index(name: 'idx_site_text_version_code', columns: ['code'])]
class SiteTextVersion
{
    public const PUBLICATION = 'publication';
    public const ORIGINE = 'origine';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 190)]
    private string $code;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(length: 20)]
    private string $kind;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    public function __construct(string $code, string $content, string $kind, ?User $author)
    {
        $this->code = $code;
        $this->content = $content;
        $this->kind = $kind;
        $this->author = $author;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCode(): string { return $this->code; }
    public function getContent(): string { return $this->content; }
    public function getKind(): string { return $this->kind; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getAuthor(): ?User { return $this->author; }
}
