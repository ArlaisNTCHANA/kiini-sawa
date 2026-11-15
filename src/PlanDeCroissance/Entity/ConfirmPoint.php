<?php

namespace App\PlanDeCroissance\Entity;

use App\PlanDeCroissance\Repository\ConfirmPointRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Coaching\Entity\AdviceRequest;

#[ORM\Entity(repositoryClass: ConfirmPointRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ConfirmPoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'confirmPoints')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanDeCroissance $planDeCroissance = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $statement = null;

    #[ORM\Column(length: 50)]
    private string $status = 'PENDING'; // PENDING, CONFIRMED, REJECTED

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * @var Collection<int, Evidence>
     */
    #[ORM\OneToMany(mappedBy: 'confirmPoint', targetEntity: Evidence::class, cascade: ['persist', 'remove'])]
    private Collection $evidences;

    /**
     * @var Collection<int, AdviceRequest>
     */
    #[ORM\OneToMany(mappedBy: 'point', targetEntity: AdviceRequest::class)]
    private Collection $adviceRequests;

    public function __construct()
    {
        $this->evidences = new ArrayCollection();
        $this->adviceRequests = new ArrayCollection();
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

    public function getPlanDeCroissance(): ?PlanDeCroissance
    {
        return $this->planDeCroissance;
    }

    public function setPlanDeCroissance(?PlanDeCroissance $planDeCroissance): static
    {
        $this->planDeCroissance = $planDeCroissance;

        return $this;
    }

    public function getStatement(): ?string
    {
        return $this->statement;
    }

    public function setStatement(string $statement): static
    {
        $this->statement = $statement;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Evidence>
     */
    public function getEvidences(): Collection
    {
        return $this->evidences;
    }

    public function addEvidence(Evidence $evidence): static
    {
        if (!$this->evidences->contains($evidence)) {
            $this->evidences->add($evidence);
            $evidence->setConfirmPoint($this);
        }

        return $this;
    }

    public function removeEvidence(Evidence $evidence): static
    {
        if ($this->evidences->removeElement($evidence)) {
            // set the owning side to null (unless already changed)
            if ($evidence->getConfirmPoint() === $this) {
                $evidence->setConfirmPoint(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AdviceRequest>
     */
    public function getAdviceRequests(): Collection
    {
        return $this->adviceRequests;
    }

    public function addAdviceRequest(AdviceRequest $adviceRequest): static
    {
        if (!$this->adviceRequests->contains($adviceRequest)) {
            $this->adviceRequests->add($adviceRequest);
            $adviceRequest->setPoint($this);
        }

        return $this;
    }

    public function removeAdviceRequest(AdviceRequest $adviceRequest): static
    {
        if ($this->adviceRequests->removeElement($adviceRequest)) {
            // set the owning side to null (unless already changed)
            if ($adviceRequest->getPoint() === $this) {
                $adviceRequest->setPoint(null);
            }
        }

        return $this;
    }
}
