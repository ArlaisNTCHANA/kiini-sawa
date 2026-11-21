<?php

namespace App\Couple\Entity;

use App\Compte\Entity\User;
use App\Couple\Repository\CoupleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Espace couple : deux conjoints qui bâtissent ensemble une vision, des projets et des thèmes de croissance.
 * Les espaces personnels (plans, journal) restent privés ; seul ce qui est créé ici est commun.
 */
#[ORM\Entity(repositoryClass: CoupleRepository::class)]
class Couple
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Conjoint qui a créé l'espace et invité l'autre. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $partnerA;

    /** Conjoint invité (null tant que l'invitation n'est pas acceptée). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $partnerB = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $invitedEmail = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $inviteCode;

    /** Vision commune du couple. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $vision = null;

    /** Verset ou parole qui porte le couple. */
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $verse = null;

    /** Chaque conjoint accepte (ou non) de partager ses résultats de tests pour le rapport croisé. */
    #[ORM\Column]
    private bool $shareTestsA = true;

    #[ORM\Column]
    private bool $shareTestsB = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $joinedAt = null;

    /** Jours consécutifs d'activité partagée (défi ou méditation). */
    #[ORM\Column(options: ['default' => 0])]
    private int $streakDays = 0;

    /** Date de la dernière activité comptabilisée pour le streak. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastActivityDate = null;

    /** @var Collection<int, CoupleItem> */
    #[ORM\OneToMany(mappedBy: 'couple', targetEntity: CoupleItem::class, cascade: ['persist'])]
    #[ORM\OrderBy(['sortOrder' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct(User $partnerA, ?string $invitedEmail)
    {
        $this->partnerA = $partnerA;
        $this->invitedEmail = $invitedEmail;
        $this->inviteCode = bin2hex(random_bytes(8));
        $this->createdAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getPartnerA(): User { return $this->partnerA; }
    public function getPartnerB(): ?User { return $this->partnerB; }
    public function getInvitedEmail(): ?string { return $this->invitedEmail; }
    public function setInvitedEmail(?string $email): static { $this->invitedEmail = $email; return $this; }
    public function getInviteCode(): string { return $this->inviteCode; }
    public function getVision(): ?string { return $this->vision; }
    public function setVision(?string $vision): static { $this->vision = $vision; return $this; }
    public function getVerse(): ?string { return $this->verse; }
    public function setVerse(?string $verse): static { $this->verse = $verse; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getJoinedAt(): ?\DateTimeImmutable { return $this->joinedAt; }
    public function getStreakDays(): int { return $this->streakDays; }
    public function getLastActivityDate(): ?\DateTimeImmutable { return $this->lastActivityDate; }

    /** Enregistre une activité partagée aujourd'hui et met à jour la flamme (streak). */
    public function recordActivity(?\DateTimeImmutable $today = null): void
    {
        $day = ($today ?? new \DateTimeImmutable('today'))->setTime(0, 0);
        if (null !== $this->lastActivityDate) {
            $last = $this->lastActivityDate->setTime(0, 0);
            if ($last == $day) {
                return;
            }
            $diff = (int) $last->diff($day)->format('%r%a');
            $this->streakDays = (1 === $diff) ? $this->streakDays + 1 : 1;
        } else {
            $this->streakDays = 1;
        }
        $this->lastActivityDate = $day;
    }

    /** @return Collection<int, CoupleItem> */
    public function getItems(): Collection { return $this->items; }

    public function isActive(): bool { return null !== $this->partnerB; }

    public function join(User $partnerB, bool $shareTests): void
    {
        $this->partnerB = $partnerB;
        $this->shareTestsB = $shareTests;
        $this->joinedAt = new \DateTimeImmutable();
        $this->invitedEmail = null;
    }

    public function has(User $user): bool
    {
        return $user === $this->partnerA || $user === $this->partnerB;
    }

    public function partnerOf(User $user): ?User
    {
        return $user === $this->partnerA ? $this->partnerB : ($user === $this->partnerB ? $this->partnerA : null);
    }

    public function sharesTests(User $user): bool
    {
        return $user === $this->partnerA ? $this->shareTestsA : $this->shareTestsB;
    }

    public function setSharesTests(User $user, bool $share): void
    {
        $user === $this->partnerA ? $this->shareTestsA = $share : $this->shareTestsB = $share;
    }

    /** @return CoupleItem[] */
    public function itemsOfKind(string $kind): array
    {
        return array_values(array_filter($this->items->toArray(), fn (CoupleItem $i) => $i->getKind() === $kind));
    }
}
