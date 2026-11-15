<?php

namespace App\Entity;

use App\Repository\AdviceResponseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AdviceResponseRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AdviceResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'responses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?AdviceRequest $request = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $providerEmail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $q1Observation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $q2Risk = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $q3Advice = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
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

    public function getRequest(): ?AdviceRequest
    {
        return $this->request;
    }

    public function setRequest(?AdviceRequest $request): static
    {
        $this->request = $request;

        return $this;
    }

    public function getProviderName(): ?string
    {
        return $this->providerName;
    }

    public function setProviderName(?string $providerName): static
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getProviderEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function setProviderEmail(?string $providerEmail): static
    {
        $this->providerEmail = $providerEmail;

        return $this;
    }

    public function getQ1Observation(): ?string
    {
        return $this->q1Observation;
    }

    public function setQ1Observation(?string $q1Observation): static
    {
        $this->q1Observation = $q1Observation;

        return $this;
    }

    public function getQ2Risk(): ?string
    {
        return $this->q2Risk;
    }

    public function setQ2Risk(?string $q2Risk): static
    {
        $this->q2Risk = $q2Risk;

        return $this;
    }

    public function getQ3Advice(): ?string
    {
        return $this->q3Advice;
    }

    public function setQ3Advice(?string $q3Advice): static
    {
        $this->q3Advice = $q3Advice;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }
}
