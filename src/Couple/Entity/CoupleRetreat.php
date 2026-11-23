<?php

namespace App\Couple\Entity;

use App\Compte\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * « Temps de retraite » du couple : pendant ces dates, plusieurs jours de défi et plusieurs étapes de méditation
 * peuvent être vécus le même jour. Proposé par un conjoint, il ne compte qu'une fois confirmé par l'autre.
 * La date de fin est obligatoire : la retraite se termine d'elle-même (décision du fondateur, 23/11/2026).
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_couple_retreat_dates', columns: ['couple_id', 'ends_on'])]
class CoupleRetreat
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Couple $couple;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $proposedBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $confirmedBy = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startsOn;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $endsOn;

    /** Intention ou lieu de la retraite (facultatif), ex. « Week-end à Kribi ». */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct(Couple $couple, User $proposedBy, \DateTimeImmutable $startsOn, \DateTimeImmutable $endsOn, ?string $note = null)
    {
        $this->couple = $couple;
        $this->proposedBy = $proposedBy;
        $this->startsOn = $startsOn->setTime(0, 0);
        $this->endsOn = $endsOn->setTime(0, 0);
        $clean = null !== $note ? trim($note) : '';
        $this->note = '' !== $clean ? mb_substr($clean, 0, 255) : null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCouple(): Couple { return $this->couple; }
    public function getProposedBy(): ?User { return $this->proposedBy; }
    public function getConfirmedBy(): ?User { return $this->confirmedBy; }
    public function getStartsOn(): \DateTimeImmutable { return $this->startsOn; }
    public function getEndsOn(): \DateTimeImmutable { return $this->endsOn; }
    public function getNote(): ?string { return $this->note; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getConfirmedAt(): ?\DateTimeImmutable { return $this->confirmedAt; }
    public function getCancelledAt(): ?\DateTimeImmutable { return $this->cancelledAt; }

    public function isConfirmed(): bool { return null !== $this->confirmedAt; }
    public function isCancelled(): bool { return null !== $this->cancelledAt; }

    public function confirm(User $by): void
    {
        $this->confirmedBy = $by;
        $this->confirmedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->cancelledAt = new \DateTimeImmutable();
    }

    /** En vigueur ce jour-là : confirmée, non arrêtée, et le jour est entre les deux dates. */
    public function isActiveOn(\DateTimeImmutable $day): bool
    {
        $d = $day->setTime(0, 0);

        return $this->isConfirmed() && !$this->isCancelled() && $this->startsOn <= $d && $d <= $this->endsOn;
    }

    /** Pas encore terminée (en attente, à venir ou en cours). */
    public function isOpenOn(\DateTimeImmutable $day): bool
    {
        return !$this->isCancelled() && $day->setTime(0, 0) <= $this->endsOn;
    }
}
