<?php

namespace App\Coaching\Entity;

use App\Coaching\Repository\AdviceRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\ConfirmPoint;

#[ORM\Entity(repositoryClass: AdviceRequestRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AdviceRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'adviceRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'adviceRequests')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ConfirmPoint $point = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $message = null;

    #[ORM\OneToMany(mappedBy: 'request', targetEntity: AdviceResponse::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $responses;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->responses = new ArrayCollection();
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getPoint(): ?ConfirmPoint
    {
        return $this->point;
    }

    public function setPoint(?ConfirmPoint $point): static
    {
        $this->point = $point;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @return Collection<int, AdviceResponse>
     */
    public function getResponses(): Collection
    {
        return $this->responses;
    }

    public function addResponse(AdviceResponse $response): static
    {
        if (!$this->responses->contains($response)) {
            $this->responses->add($response);
            $response->setRequest($this);
        }

        return $this;
    }

    public function removeResponse(AdviceResponse $response): static
    {
        if ($this->responses->removeElement($response)) {
            // set the owning side to null (unless already changed)
            if ($response->getRequest() === $this) {
                $response->setRequest(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
