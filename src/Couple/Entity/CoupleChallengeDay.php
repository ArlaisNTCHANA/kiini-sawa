<?php

namespace App\Couple\Entity;

use App\Compte\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Validation quotidienne croisée d'un défi de couple (conjoint A et conjoint B).
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_couple_challenge_day', columns: ['challenge_id', 'day_number'])]
class CoupleChallengeDay
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'days')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private CoupleChallenge $challenge;

    #[ORM\Column]
    private int $dayNumber;

    #[ORM\Column]
    private bool $validatedByPartnerA = false;

    #[ORM\Column]
    private bool $validatedByPartnerB = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $noteA = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $noteB = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null;

    public function __construct(CoupleChallenge $challenge, int $dayNumber)
    {
        $this->challenge = $challenge;
        $this->dayNumber = $dayNumber;
    }

    public function getId(): ?int { return $this->id; }
    public function getChallenge(): CoupleChallenge { return $this->challenge; }
    public function getDayNumber(): int { return $this->dayNumber; }
    public function isValidatedByPartnerA(): bool { return $this->validatedByPartnerA; }
    public function isValidatedByPartnerB(): bool { return $this->validatedByPartnerB; }
    public function getNoteA(): ?string { return $this->noteA; }
    public function getNoteB(): ?string { return $this->noteB; }
    public function getValidatedAt(): ?\DateTimeImmutable { return $this->validatedAt; }

    public function isBothValidated(): bool
    {
        return $this->validatedByPartnerA && $this->validatedByPartnerB;
    }

    public function isValidatedBy(User $user): bool
    {
        $couple = $this->challenge->getCouple();

        return $user === $couple->getPartnerA() ? $this->validatedByPartnerA : $this->validatedByPartnerB;
    }

    public function validateFor(User $user, ?string $note = null): void
    {
        $couple = $this->challenge->getCouple();
        $cleanNote = null !== $note ? trim($note) : null;
        if ('' === $cleanNote) {
            $cleanNote = null;
        }

        if ($user === $couple->getPartnerA()) {
            $this->validatedByPartnerA = true;
            if (null !== $cleanNote) {
                $this->noteA = mb_substr($cleanNote, 0, 1000);
            }
        } elseif ($user === $couple->getPartnerB()) {
            $this->validatedByPartnerB = true;
            if (null !== $cleanNote) {
                $this->noteB = mb_substr($cleanNote, 0, 1000);
            }
        }

        $this->validatedAt = new \DateTimeImmutable();
    }
}
