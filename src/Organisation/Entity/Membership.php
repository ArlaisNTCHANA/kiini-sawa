<?php

namespace App\Organisation\Entity;

use App\Compte\Entity\User;
use App\Organisation\Repository\MembershipRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Appartenance d'un utilisateur à une organisation.
 *
 * Rôles : OWNER (gère tout, y compris l'achat de places), LEADER (voit et gère les membres),
 * MENTOR (accompagnateur : ne voit que les membres qui lui sont confiés), MEMBER.
 * Le contenu des plans n'est visible que si le membre y consent (sharePlans) ; le journal jamais.
 */
#[ORM\Entity(repositoryClass: MembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_membership_org_user', columns: ['organisation_id', 'user_id'])]
class Membership
{
    public const ROLES = [
        'OWNER' => 'Responsable principal',
        'LEADER' => 'Responsable',
        'MENTOR' => 'Accompagnateur',
        'MEMBER' => 'Membre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organisation $organisation;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 10)]
    private string $role = 'MEMBER';

    /** Accompagnateur à qui ce membre est confié. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Membership $mentor = null;

    /** Le membre accepte que les responsables et son accompagnateur lisent ses plans. */
    #[ORM\Column]
    private bool $sharePlans = false;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $joinedAt;

    public function __construct(Organisation $organisation, User $user, string $role = 'MEMBER')
    {
        $this->organisation = $organisation;
        $this->user = $user;
        $this->role = isset(self::ROLES[$role]) ? $role : 'MEMBER';
        $this->joinedAt = new \DateTimeImmutable();
        $organisation->getMemberships()->add($this);
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganisation(): Organisation { return $this->organisation; }
    public function getUser(): User { return $this->user; }
    public function getRole(): string { return $this->role; }
    public function getRoleLabel(): string { return self::ROLES[$this->role]; }
    public function setRole(string $role): static { $this->role = isset(self::ROLES[$role]) ? $role : $this->role; return $this; }
    public function getMentor(): ?Membership { return $this->mentor; }
    public function setMentor(?Membership $mentor): static { $this->mentor = $mentor; return $this; }
    public function isSharePlans(): bool { return $this->sharePlans; }
    public function setSharePlans(bool $sharePlans): static { $this->sharePlans = $sharePlans; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }
    public function getJoinedAt(): \DateTimeImmutable { return $this->joinedAt; }
    public function rejoin(): static { $this->active = true; $this->joinedAt = new \DateTimeImmutable(); return $this; }

    public function isManager(): bool { return \in_array($this->role, ['OWNER', 'LEADER'], true); }
    public function isOwner(): bool { return 'OWNER' === $this->role; }
}
