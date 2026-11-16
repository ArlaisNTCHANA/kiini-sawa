<?php

namespace App\Media\Entity;

use App\Compte\Entity\User;
use App\Media\Repository\ReferralRepository;
use Doctrine\ORM\Mapping as ORM;

/** Parrainage : qui a fait venir qui, et si la récompense a été accordée (à la vérification de l'email du filleul). */
#[ORM\Entity(repositoryClass: ReferralRepository::class)]
#[ORM\Index(name: 'idx_referral_referrer', columns: ['referrer_id'])]
class Referral
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $referrer;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $referred;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rewardedAt = null;

    public function __construct(User $referrer, User $referred)
    {
        $this->referrer = $referrer;
        $this->referred = $referred;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getReferrer(): User { return $this->referrer; }
    public function getReferred(): User { return $this->referred; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getRewardedAt(): ?\DateTimeImmutable { return $this->rewardedAt; }
    public function markRewarded(): static { $this->rewardedAt = new \DateTimeImmutable(); return $this; }
}
