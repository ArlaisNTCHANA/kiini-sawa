<?php

namespace App\Social\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une interpellation : l'e-mail envoyé au partenaire (et en copie à la personne), puis la réponse du partenaire,
 * donnée en un clic depuis un lien personnel, sans compte.
 */
#[ORM\Entity]
#[ORM\Index(name: 'idx_accountability_nudge_sent', columns: ['commitment_id', 'sent_at'])]
class AccountabilityNudge
{
    public const REASON_OWNER_REQUEST = 'OWNER_REQUEST';

    /** Actions que le partenaire peut indiquer. */
    public const ACTIONS = [
        'CALLED' => 'Je l\'ai appelé(e)',
        'WROTE' => 'Je lui ai écrit',
        'MET' => 'Nous nous sommes vus',
        'PRAYED' => 'J\'ai prié pour lui/elle',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private AccountabilityCommitment $commitment;

    #[ORM\Column(length: 20)]
    private string $reason;

    /** Mot de la personne pour son partenaire (« ce que je traverse »), facultatif. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ownerMessage;

    #[ORM\Column(length: 48, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $responseAction = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseNote = null;

    public function __construct(AccountabilityCommitment $commitment, string $reason, ?string $ownerMessage = null)
    {
        $this->commitment = $commitment;
        $this->reason = $reason;
        $clean = null !== $ownerMessage ? trim($ownerMessage) : '';
        $this->ownerMessage = '' !== $clean ? mb_substr($clean, 0, 1000) : null;
        $this->token = bin2hex(random_bytes(24));
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCommitment(): AccountabilityCommitment { return $this->commitment; }
    public function getReason(): string { return $this->reason; }
    public function getOwnerMessage(): ?string { return $this->ownerMessage; }
    public function getToken(): string { return $this->token; }
    public function getSentAt(): \DateTimeImmutable { return $this->sentAt; }
    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }
    public function getResponseAction(): ?string { return $this->responseAction; }
    public function getResponseActionLabel(): ?string { return null !== $this->responseAction ? (self::ACTIONS[$this->responseAction] ?? null) : null; }
    public function getResponseNote(): ?string { return $this->responseNote; }

    public function respond(string $action, ?string $note): void
    {
        $this->responseAction = $action;
        $clean = null !== $note ? trim($note) : '';
        $this->responseNote = '' !== $clean ? mb_substr($clean, 0, 1000) : null;
        $this->respondedAt = new \DateTimeImmutable();
    }
}
