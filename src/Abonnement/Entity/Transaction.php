<?php

namespace App\Abonnement\Entity;

use App\Abonnement\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Subscription $subscription = null;

    #[ORM\Column]
    private ?float $amount = null;

    #[ORM\Column(length: 10)]
    private string $currency = 'EUR';

    #[ORM\Column(length: 50)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripeInvoiceId = null;

    /** Moyen de paiement : BANK_TRANSFER, MOBILE_MONEY, CASH, CARD, GIFT (offert par l'administration). */
    #[ORM\Column(length: 30, options: ['default' => 'BANK_TRANSFER'])]
    private string $method = 'BANK_TRANSFER';

    /** Période achetée : MONTHLY ou YEARLY. */
    #[ORM\Column(length: 10, options: ['default' => 'MONTHLY'])]
    private string $period = 'MONTHLY';

    /** Référence donnée par le client (n° de transaction Mobile Money, libellé du virement…). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reference = null;

    /** Date du paiement déclarée par le client. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** Numéro de reçu (attribué à la validation, séquentiel par année). */
    #[ORM\Column(length: 30, nullable: true, unique: true)]
    private ?string $receiptNumber = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    /** Motif du refus, ou note de l'administrateur. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adminNote = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function setSubscription(?Subscription $subscription): static
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(float $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStripeInvoiceId(): ?string
    {
        return $this->stripeInvoiceId;
    }

    public function setStripeInvoiceId(?string $stripeInvoiceId): static
    {
        $this->stripeInvoiceId = $stripeInvoiceId;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VALIDATED = 'VALIDATED';
    public const STATUS_REJECTED = 'REJECTED';

    public const METHODS = [
        'BANK_TRANSFER' => 'Virement bancaire',
        'MOBILE_MONEY' => 'Mobile Money',
        'CASH' => 'Espèces',
        'CARD' => 'Carte bancaire',
        'GIFT' => 'Offert',
    ];

    public function getMethod(): string { return $this->method; }
    public function setMethod(string $method): static { $this->method = $method; return $this; }
    public function getMethodLabel(): string { return self::METHODS[$this->method] ?? $this->method; }
    public function getPeriod(): string { return $this->period; }
    public function setPeriod(string $period): static { $this->period = $period; return $this; }
    public function getReference(): ?string { return $this->reference; }
    public function setReference(?string $reference): static { $this->reference = $reference; return $this; }
    public function getPaidAt(): ?\DateTimeImmutable { return $this->paidAt; }
    public function setPaidAt(?\DateTimeImmutable $paidAt): static { $this->paidAt = $paidAt; return $this; }
    public function getReceiptNumber(): ?string { return $this->receiptNumber; }
    public function setReceiptNumber(?string $receiptNumber): static { $this->receiptNumber = $receiptNumber; return $this; }
    public function getProcessedAt(): ?\DateTimeImmutable { return $this->processedAt; }
    public function setProcessedAt(?\DateTimeImmutable $processedAt): static { $this->processedAt = $processedAt; return $this; }
    public function getAdminNote(): ?string { return $this->adminNote; }
    public function setAdminNote(?string $adminNote): static { $this->adminNote = $adminNote; return $this; }
    public function isPending(): bool { return self::STATUS_PENDING === $this->status; }
}
