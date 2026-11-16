<?php

namespace App\Media\Entity;

use App\Compte\Entity\User;
use App\Media\Repository\SignupOriginRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Provenance d'un inscrit : lien suivi (utm_*), site d'origine ou invitation. Sert à savoir quels contenus
 * et quels canaux font venir des inscrits et des abonnés.
 */
#[ORM\Entity(repositoryClass: SignupOriginRepository::class)]
#[ORM\Index(name: 'idx_signup_origin_source', columns: ['source'])]
#[ORM\Index(name: 'idx_signup_origin_created', columns: ['created_at'])]
class SignupOrigin
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $user;

    /** Canal normalisé : tiktok, instagram, facebook, youtube, whatsapp, newsletter, google, site-vitrine, invitation, direct, autre… */
    #[ORM\Column(length: 60)]
    private string $source;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $medium = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $campaign = null;

    /** Contenu précis (utm_content), ex. identifiant de la vidéo */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $content = null;

    /** Domaine du site d'où venait le visiteur, sans le chemin */
    #[ORM\Column(length: 190, nullable: true)]
    private ?string $referrer = null;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $landingPath = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $source)
    {
        $this->user = $user;
        $this->source = $source;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getSource(): string { return $this->source; }
    public function getMedium(): ?string { return $this->medium; }
    public function setMedium(?string $v): static { $this->medium = $v; return $this; }
    public function getCampaign(): ?string { return $this->campaign; }
    public function setCampaign(?string $v): static { $this->campaign = $v; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $v): static { $this->content = $v; return $this; }
    public function getReferrer(): ?string { return $this->referrer; }
    public function setReferrer(?string $v): static { $this->referrer = $v; return $this; }
    public function getLandingPath(): ?string { return $this->landingPath; }
    public function setLandingPath(?string $v): static { $this->landingPath = $v; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
