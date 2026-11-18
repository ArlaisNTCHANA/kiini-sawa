<?php

namespace App\Rapide\Entity;

use App\Compte\Entity\User;
use App\Rapide\Repository\SyncOperationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une opération reçue du mode rapide (saisie faite hors ligne). L'identifiant est créé par le téléphone : l'envoyer deux fois
 * (réseau coupé au mauvais moment) ne l'applique qu'une fois.
 */
#[ORM\Entity(repositoryClass: SyncOperationRepository::class)]
#[ORM\Table(name: 'sync_operation')]
#[ORM\UniqueConstraint(name: 'uniq_sync_operation_user_uid', columns: ['user_id', 'uid'])]
class SyncOperation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64)]
    private string $uid;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $appliedAt;

    public function __construct(User $user, string $uid, string $type)
    {
        $this->user = $user;
        $this->uid = $uid;
        $this->type = $type;
        $this->appliedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUid(): string { return $this->uid; }
    public function getType(): string { return $this->type; }
    public function getAppliedAt(): \DateTimeImmutable { return $this->appliedAt; }
}
