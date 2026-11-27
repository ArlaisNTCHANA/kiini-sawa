<?php

namespace App\Notification\Entity;

use App\Compte\Entity\User;
use App\Notification\Repository\AnnouncementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Annonce de l'équipe à tous les membres ou à un groupe (Admin › Écrire aux membres, 27/11/2026).
 *
 * Envoyée par lots (AnnouncementSender) : `cursor` retient le dernier membre traité, et la clé de notification
 * « annonce-<id> » garantit que personne ne la reçoit deux fois, même si l'envoi est interrompu puis repris.
 */
#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
#[ORM\Table(name: 'announcement')]
class Announcement
{
    /** Publics possibles : code => libellé. */
    public const AUDIENCES = [
        'ALL' => 'Tous les membres',
        'FREE' => 'Les membres en offre gratuite',
        'TRIAL' => 'Les membres en essai PRO',
        'PRO' => 'Les membres PRO',
        'INACTIVE' => 'Les membres absents depuis 30 jours',
        'ORGANISATION' => 'Les membres d\'une organisation',
    ];

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SENDING = 'SENDING';
    public const STATUS_SENT = 'SENT';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $actionLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionUrl = null;

    #[ORM\Column(length: 20)]
    private string $audience = 'ALL';

    #[ORM\Column(nullable: true)]
    private ?int $organisationId = null;

    #[ORM\Column]
    private bool $withEmail = true;

    #[ORM\Column(length: 10)]
    private string $status = self::STATUS_DRAFT;

    /** Dernier identifiant de membre traité (reprise de l'envoi). */
    #[ORM\Column(name: 'last_user_id')] // « cursor » est un mot réservé de MariaDB
    private int $cursor = 0;

    #[ORM\Column]
    private int $reached = 0;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $author = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getActionLabel(): ?string { return $this->actionLabel; }
    public function setActionLabel(?string $actionLabel): static { $this->actionLabel = $actionLabel; return $this; }
    public function getActionUrl(): ?string { return $this->actionUrl; }
    public function setActionUrl(?string $actionUrl): static { $this->actionUrl = $actionUrl; return $this; }
    public function getAudience(): string { return $this->audience; }
    public function setAudience(string $audience): static { $this->audience = $audience; return $this; }
    public function getAudienceLabel(): string { return self::AUDIENCES[$this->audience] ?? $this->audience; }
    public function getOrganisationId(): ?int { return $this->organisationId; }
    public function setOrganisationId(?int $organisationId): static { $this->organisationId = $organisationId; return $this; }
    public function isWithEmail(): bool { return $this->withEmail; }
    public function setWithEmail(bool $withEmail): static { $this->withEmail = $withEmail; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function isDraft(): bool { return self::STATUS_DRAFT === $this->status; }
    public function isSending(): bool { return self::STATUS_SENDING === $this->status; }
    public function getCursor(): int { return $this->cursor; }
    public function setCursor(int $cursor): static { $this->cursor = $cursor; return $this; }
    public function getReached(): int { return $this->reached; }
    public function addReached(int $n): static { $this->reached += $n; return $this; }
    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(?User $author): static { $this->author = $author; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(?\DateTimeImmutable $sentAt): static { $this->sentAt = $sentAt; return $this; }
    public function key(): string { return 'annonce-'.$this->id; }
}
