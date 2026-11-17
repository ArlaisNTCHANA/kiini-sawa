<?php

namespace App\Personnalite\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Réponse d'un proche à une demande de confirmation (anonyme par défaut). */
#[ORM\Entity]
#[ORM\Table(name: 'confirmation_answer')]
class ConfirmationAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ConfirmationRequest $request;

    #[ORM\Column(length: 30)]
    private string $relationship;

    /** DISC : {most, least} ; dons : {gifts: [...]} */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $name;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(ConfirmationRequest $request, string $relationship, array $payload, ?string $name)
    {
        $this->request = $request;
        $this->relationship = $relationship;
        $this->payload = $payload;
        $this->name = $name ? mb_substr(trim($name), 0, 100) : null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getRequest(): ConfirmationRequest { return $this->request; }
    public function getRelationship(): string { return $this->relationship; }
    public function getPayload(): array { return $this->payload; }
    public function getName(): ?string { return $this->name; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
