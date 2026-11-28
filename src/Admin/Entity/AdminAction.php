<?php

namespace App\Admin\Entity;

use App\Compte\Entity\User;
use Doctrine\ORM\Mapping as ORM;

/**
 * Journal des actions d'administration (27/11/2026) : qui a fait quoi, quand, et pour qui. Écrit automatiquement par
 * AdminActionRecorder pour chaque action réussie (le message de réussite affiché à l'administrateur). Gardé même si
 * l'administrateur ou le membre concerné est supprimé (l'adresse est recopiée).
 */
#[ORM\Entity(repositoryClass: \App\Admin\Repository\AdminActionRepository::class)]
#[ORM\Table(name: 'admin_action')]
#[ORM\Index(columns: ['created_at'], name: 'idx_admin_action_created')]
class AdminAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $actor = null;

    #[ORM\Column(length: 180)]
    private string $actorEmail = '';

    #[ORM\Column(length: 80)]
    private string $route = '';

    #[ORM\Column(length: 255)]
    private string $message = '';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $target = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getActor(): ?User { return $this->actor; }
    public function getActorEmail(): string { return $this->actorEmail; }
    public function getRoute(): string { return $this->route; }
    public function getMessage(): string { return $this->message; }
    public function getTarget(): ?string { return $this->target; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
