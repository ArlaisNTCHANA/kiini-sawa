<?php

namespace App\Personnalite\Entity;

use App\Compte\Entity\User;
use App\Personnalite\Repository\TestActionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Action d'un rapport de test ajoutée par l'utilisateur à ses rituels ou à son Plan de Croissance.
 * Sert au rappel à 30 jours (« Où en êtes-vous ? Refaites le point »).
 */
#[ORM\Entity(repositoryClass: TestActionRepository::class)]
#[ORM\Table(name: 'test_action')]
#[ORM\Index(name: 'idx_test_action_user', columns: ['user_id'])]
class TestAction
{
    public const TARGETS = ['habit' => 'mes rituels', 'growth' => 'mon Plan de Croissance'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $text;

    #[ORM\Column(length: 60)]
    private string $source;

    #[ORM\Column(length: 20)]
    private string $target;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $remindedAt = null;

    public function __construct(User $user, string $text, string $source, string $target)
    {
        $this->user = $user;
        $this->text = mb_substr($text, 0, 255);
        $this->source = mb_substr($source, 0, 60);
        $this->target = $target;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getText(): string { return $this->text; }
    public function getSource(): string { return $this->source; }
    public function getTarget(): string { return $this->target; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $d): static { $this->createdAt = $d; return $this; }
    public function getRemindedAt(): ?\DateTimeImmutable { return $this->remindedAt; }
    public function markReminded(): void { $this->remindedAt = new \DateTimeImmutable(); }
}
