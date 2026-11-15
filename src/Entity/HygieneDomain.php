<?php

namespace App\Entity;

use App\Repository\HygieneDomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HygieneDomainRepository::class)]
#[ORM\HasLifecycleCallbacks]
class HygieneDomain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'hygieneDomains')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanDHygiene $planDHygiene = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $targetScore = 100;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $subtype = null;

    /**
     * @var Collection<int, Habit>
     */
    #[ORM\OneToMany(mappedBy: 'hygieneDomain', targetEntity: Habit::class)]
    private Collection $habits;

    /**
     * @var Collection<int, Comment>
     */
    #[ORM\OneToMany(mappedBy: 'hygieneDomain', targetEntity: Comment::class, cascade: ['persist', 'remove'])]
    private Collection $comments;

    /**
     * @var Collection<int, LifeGoal>
     */
    #[ORM\ManyToMany(targetEntity: LifeGoal::class, inversedBy: 'supportingHygieneDomains')]
    #[ORM\JoinTable(name: 'hygiene_domain_life_goal')]
    private Collection $supportedLifeGoals;

    public function __construct()
    {
        $this->habits = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->supportedLifeGoals = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanDHygiene(): ?PlanDHygiene
    {
        return $this->planDHygiene;
    }

    public function setPlanDHygiene(?PlanDHygiene $planDHygiene): static
    {
        $this->planDHygiene = $planDHygiene;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getTargetScore(): int
    {
        return $this->targetScore;
    }

    public function setTargetScore(int $targetScore): static
    {
        $this->targetScore = $targetScore;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, Habit>
     */
    public function getHabits(): Collection
    {
        return $this->habits;
    }

    public function addHabit(Habit $habit): static
    {
        if (!$this->habits->contains($habit)) {
            $this->habits->add($habit);
            $habit->setHygieneDomain($this);
        }

        return $this;
    }

    public function removeHabit(Habit $habit): static
    {
        if ($this->habits->removeElement($habit)) {
            // set the owning side to null (unless already changed)
            if ($habit->getHygieneDomain() === $this) {
                $habit->setHygieneDomain(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Comment>
     */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setHygieneDomain($this);
        }

        return $this;
    }

    public function removeComment(Comment $comment): static
    {
        if ($this->comments->removeElement($comment)) {
            // set the owning side to null (unless already changed)
            if ($comment->getHygieneDomain() === $this) {
                $comment->setHygieneDomain(null);
            }
        }

        return $this;
    }

    public function getSubtype(): ?string
    {
        return $this->subtype;
    }

    public function setSubtype(?string $subtype): static
    {
        $this->subtype = $subtype;

        return $this;
    }

    public function getParentDomain(): string
    {
        $mapping = [
            'Sommeil & Énergie' => 'Physique',
            'Nutrition & Digestion' => 'Physique',
            'Hydratation' => 'Physique',
            'Activité physique' => 'Physique',
            'Repos cognitif' => 'Mental',
            'Gestion du stress' => 'Mental',
            'Calme & Loisirs' => 'Mental',
            'Clarté mentale' => 'Mental',
            'Écoute active' => 'Relationnel',
            'Partage & Moments' => 'Relationnel',
            'Expression des besoins' => 'Relationnel',
            'Soutien & Réseau' => 'Relationnel',
            'Sens & Utilité' => 'Spirituel',
            'Alignement' => 'Spirituel',
            'Paix intérieure' => 'Spirituel',
            'Pratique personnelle' => 'Spirituel',
            'Gestion du temps' => 'Ressources',
            'Projets personnels' => 'Ressources',
            'Limites d’énergie' => 'Ressources',
            'Récupération' => 'Ressources',
            'Clarté de l’espace' => 'Environnement',
            'Lumière naturelle' => 'Environnement',
            'Niveau sonore' => 'Environnement',
            'Contact avec la nature' => 'Environnement',
        ];

        return $mapping[$this->name] ?? 'Autre';
    }

    /**
     * @return Collection<int, LifeGoal>
     */
    public function getSupportedLifeGoals(): Collection
    {
        return $this->supportedLifeGoals;
    }

    public function addSupportedLifeGoal(LifeGoal $lifeGoal): static
    {
        if (!$this->supportedLifeGoals->contains($lifeGoal)) {
            $this->supportedLifeGoals->add($lifeGoal);
        }

        return $this;
    }

    public function removeSupportedLifeGoal(LifeGoal $lifeGoal): static
    {
        $this->supportedLifeGoals->removeElement($lifeGoal);

        return $this;
    }
}
