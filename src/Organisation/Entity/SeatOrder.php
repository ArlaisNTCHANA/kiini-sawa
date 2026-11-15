<?php

namespace App\Organisation\Entity;

use App\Compte\Entity\User;
use App\Organisation\Repository\SeatOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Commande d'un pack de places pour un an, payée par virement / Mobile Money et validée dans l'admin.
 */
#[ORM\Entity(repositoryClass: SeatOrderRepository::class)]
class SeatOrder
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_REJECTED = 'REJECTED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organisation $organisation;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $orderedBy;

    #[ORM\Column]
    private int $seats;

    #[ORM\Column]
    private float $amount;

    #[ORM\Column(length: 10)]
    private string $currency;

    #[ORM\Column(length: 30)]
    private string $method;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference;

    #[ORM\Column(length: 10)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(length: 30, nullable: true, unique: true)]
    private ?string $receiptNumber = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    public function __construct(Organisation $organisation, ?User $orderedBy, int $seats, float $amount, string $currency, string $method, ?string $reference)
    {
        $this->organisation = $organisation;
        $this->orderedBy = $orderedBy;
        $this->seats = $seats;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->method = $method;
        $this->reference = $reference;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganisation(): Organisation { return $this->organisation; }
    public function getOrderedBy(): ?User { return $this->orderedBy; }
    public function getSeats(): int { return $this->seats; }
    public function getAmount(): float { return $this->amount; }
    public function getCurrency(): string { return $this->currency; }
    public function getMethod(): string { return $this->method; }
    public function getMethodLabel(): string { return \App\Abonnement\Entity\Transaction::METHODS[$this->method] ?? $this->method; }
    public function getReference(): ?string { return $this->reference; }
    public function getStatus(): string { return $this->status; }
    public function isPending(): bool { return self::STATUS_PENDING === $this->status; }
    public function getReceiptNumber(): ?string { return $this->receiptNumber; }
    public function getAdminNote(): ?string { return $this->adminNote; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getProcessedAt(): ?\DateTimeImmutable { return $this->processedAt; }

    public function validate(string $receiptNumber, ?string $note): void
    {
        $this->status = self::STATUS_VALIDATED;
        $this->receiptNumber = $receiptNumber;
        $this->adminNote = $note;
        $this->processedAt = new \DateTimeImmutable();
    }

    public function reject(string $reason): void
    {
        $this->status = self::STATUS_REJECTED;
        $this->adminNote = $reason;
        $this->processedAt = new \DateTimeImmutable();
    }
}
