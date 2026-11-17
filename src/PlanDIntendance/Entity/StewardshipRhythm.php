<?php

namespace App\PlanDIntendance\Entity;

use App\Compte\Entity\User;
use App\PlanDIntendance\Repository\StewardshipRhythmRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rythme de compte rendu du Plan d'Intendance : quand je fais le point, et avec qui (livre, chapitre 18).
 */
#[ORM\Entity(repositoryClass: StewardshipRhythmRepository::class)]
class StewardshipRhythm
{
    public const FREQUENCIES = ['WEEKLY' => 'Chaque semaine', 'MONTHLY' => 'Chaque mois', 'SEASON' => 'À chaque saison (3 mois)'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 10)]
    private string $frequency = 'MONTHLY';

    /** Avec qui je fais le point (texte libre : conjoint, mentor, partenaire…). */
    #[ORM\Column(length: 120, nullable: true)]
    private ?string $withWhom = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastReviewAt = null;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(User $user): static { $this->user = $user; return $this; }

    public function getFrequency(): string { return $this->frequency; }

    public function setFrequency(string $frequency): static
    {
        $this->frequency = isset(self::FREQUENCIES[$frequency]) ? $frequency : 'MONTHLY';

        return $this;
    }

    public function getWithWhom(): ?string { return $this->withWhom; }

    public function setWithWhom(?string $withWhom): static
    {
        $this->withWhom = '' === trim((string) $withWhom) ? null : mb_substr(trim((string) $withWhom), 0, 120);

        return $this;
    }

    public function getLastReviewAt(): ?\DateTimeImmutable { return $this->lastReviewAt; }

    public function setLastReviewAt(?\DateTimeImmutable $at): static { $this->lastReviewAt = $at; return $this; }

    /** Date du prochain point, ou null si aucun n'a encore été fait. */
    public function nextReviewAt(): ?\DateTimeImmutable
    {
        return $this->lastReviewAt?->modify(['WEEKLY' => '+7 days', 'MONTHLY' => '+1 month', 'SEASON' => '+3 months'][$this->frequency]);
    }
}
