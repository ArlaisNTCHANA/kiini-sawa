<?php

namespace App\Social\Entity;

use App\Compte\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Engagement de redevabilité : une personne demande à un partenaire (avec ou sans compte) de l'interpeller sur un
 * objectif, un rituel ou un engagement libre. Rien n'est envoyé au partenaire tant qu'il n'a pas accepté son rôle.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_accountability_owner', columns: ['owner_id', 'status'])]
class AccountabilityCommitment
{
    public const STATUS_PENDING = 'PENDING';   // en attente de l'accord du partenaire
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_PAUSED = 'PAUSED';
    public const STATUS_DECLINED = 'DECLINED'; // refusé par le partenaire
    public const STATUS_STOPPED = 'STOPPED';   // arrêté par la personne ou par le partenaire

    public const TARGET_GOAL = 'GOAL';
    public const TARGET_HABIT = 'HABIT';
    public const TARGET_FREE = 'FREE';

    /** Délai minimal entre deux interpellations, par défaut (en jours). */
    public const DEFAULT_MIN_DAYS = 7;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** Partenaire qui a un compte (facultatif : un partenaire peut ne pas être inscrit). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $partnerUser = null;

    #[ORM\Column(length: 120)]
    private string $partnerName;

    #[ORM\Column(length: 180)]
    private string $partnerEmail;

    #[ORM\Column(length: 20)]
    private string $targetType;

    #[ORM\Column(nullable: true)]
    private ?int $targetId = null;

    /** Libellé figé au moment de l'engagement (l'objectif peut être renommé ensuite). */
    #[ORM\Column(length: 255)]
    private string $targetLabel;

    /** « Pourquoi c'est important pour moi » : transmis au partenaire. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $why = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    /** Lien personnel du partenaire : accepter, refuser, ne plus recevoir. */
    #[ORM\Column(length: 48, unique: true)]
    private string $partnerToken;

    #[ORM\Column]
    private int $minDaysBetweenNudges = self::DEFAULT_MIN_DAYS;

    /** Alertes automatiques choisies par la personne (null : désactivée). Rituel non validé depuis N jours. */
    #[ORM\Column(nullable: true)]
    private ?int $alertHabitDays = null;

    /** Objectif sans mise à jour depuis N jours. */
    #[ORM\Column(nullable: true)]
    private ?int $alertGoalDays = null;

    /** Aucune connexion à KIINI SAWA depuis N jours (filet de sécurité). */
    #[ORM\Column(nullable: true)]
    private ?int $alertInactiveDays = null;

    /** La personne a lu et accepté ce qui serait envoyé (aperçu du message). */
    #[ORM\Column]
    private \DateTimeImmutable $ownerConsentAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $partnerAcceptedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    /** Qui a mis fin à l'engagement : OWNER ou PARTNER. */
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $endedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $owner, string $partnerName, string $partnerEmail, string $targetType, ?int $targetId, string $targetLabel, ?string $why)
    {
        $this->owner = $owner;
        $this->partnerName = mb_substr(trim($partnerName), 0, 120);
        $this->partnerEmail = mb_strtolower(trim($partnerEmail));
        $this->targetType = $targetType;
        $this->targetId = $targetId;
        $this->targetLabel = mb_substr(trim($targetLabel), 0, 255);
        $cleanWhy = null !== $why ? trim($why) : '';
        $this->why = '' !== $cleanWhy ? mb_substr($cleanWhy, 0, 1000) : null;
        $this->partnerToken = bin2hex(random_bytes(24));
        $this->ownerConsentAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOwner(): User { return $this->owner; }
    public function getPartnerUser(): ?User { return $this->partnerUser; }
    public function setPartnerUser(?User $user): static { $this->partnerUser = $user; return $this; }
    public function getPartnerName(): string { return $this->partnerName; }
    public function getPartnerEmail(): string { return $this->partnerEmail; }
    public function getTargetType(): string { return $this->targetType; }
    public function getTargetId(): ?int { return $this->targetId; }
    public function getTargetLabel(): string { return $this->targetLabel; }
    public function getWhy(): ?string { return $this->why; }
    public function getStatus(): string { return $this->status; }
    public function getPartnerToken(): string { return $this->partnerToken; }
    public function getMinDaysBetweenNudges(): int { return $this->minDaysBetweenNudges; }
    public function getOwnerConsentAt(): \DateTimeImmutable { return $this->ownerConsentAt; }
    public function getAlertHabitDays(): ?int { return $this->alertHabitDays; }
    public function getAlertGoalDays(): ?int { return $this->alertGoalDays; }
    public function getAlertInactiveDays(): ?int { return $this->alertInactiveDays; }

    /** Seuils bornés (2 à 90 jours) ; une alerte qui ne correspond pas à l'objet de l'engagement est ignorée. */
    public function setAlerts(?int $habitDays, ?int $goalDays, ?int $inactiveDays): static
    {
        $clamp = static fn (?int $d) => null === $d || $d <= 0 ? null : max(2, min(90, $d));
        $this->alertHabitDays = self::TARGET_HABIT === $this->targetType ? $clamp($habitDays) : null;
        $this->alertGoalDays = self::TARGET_GOAL === $this->targetType ? $clamp($goalDays) : null;
        $this->alertInactiveDays = $clamp($inactiveDays);

        return $this;
    }

    public function hasAutomaticAlerts(): bool
    {
        return null !== $this->alertHabitDays || null !== $this->alertGoalDays || null !== $this->alertInactiveDays;
    }
    public function getPartnerAcceptedAt(): ?\DateTimeImmutable { return $this->partnerAcceptedAt; }
    public function getEndedAt(): ?\DateTimeImmutable { return $this->endedAt; }
    public function getEndedBy(): ?string { return $this->endedBy; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function isPending(): bool { return self::STATUS_PENDING === $this->status; }
    public function isActive(): bool { return self::STATUS_ACTIVE === $this->status; }
    public function isPaused(): bool { return self::STATUS_PAUSED === $this->status; }
    /** Engagement encore vivant (compte dans la limite de l'offre gratuite). */
    public function isOpen(): bool { return \in_array($this->status, [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_PAUSED], true); }

    public function accept(): void
    {
        $this->status = self::STATUS_ACTIVE;
        $this->partnerAcceptedAt = new \DateTimeImmutable();
    }

    public function decline(): void
    {
        $this->status = self::STATUS_DECLINED;
        $this->endedAt = new \DateTimeImmutable();
        $this->endedBy = 'PARTNER';
    }

    public function pause(): void { $this->status = self::STATUS_PAUSED; }

    /** L'objet lié a été supprimé : l'engagement devient libre (même libellé), sans alerte liée à cet objet. */
    public function detachTarget(): void
    {
        $this->targetType = self::TARGET_FREE;
        $this->targetId = null;
        $this->alertHabitDays = null;
        $this->alertGoalDays = null;
    }
    public function resume(): void { $this->status = self::STATUS_ACTIVE; }

    public function stop(string $by): void
    {
        $this->status = self::STATUS_STOPPED;
        $this->endedAt = new \DateTimeImmutable();
        $this->endedBy = $by;
    }
}
