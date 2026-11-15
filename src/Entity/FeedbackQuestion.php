<?php

namespace App\Entity;

use App\Repository\FeedbackQuestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeedbackQuestionRepository::class)]
#[ORM\HasLifecycleCallbacks]
class FeedbackQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $labelFr = null;

    #[ORM\Column(length: 255)]
    private ?string $labelEn = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeholderFr = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $placeholderEn = null;

    #[ORM\Column(length: 50)]
    private string $type = 'text';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $options = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $isMandatory = true;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
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

    public function getLabelFr(): ?string
    {
        return $this->labelFr;
    }

    public function setLabelFr(string $labelFr): static
    {
        $this->labelFr = $labelFr;

        return $this;
    }

    public function getLabelEn(): ?string
    {
        return $this->labelEn;
    }

    public function setLabelEn(string $labelEn): static
    {
        $this->labelEn = $labelEn;

        return $this;
    }

    public function getPlaceholderFr(): ?string
    {
        return $this->placeholderFr;
    }

    public function setPlaceholderFr(?string $placeholderFr): static
    {
        $this->placeholderFr = $placeholderFr;

        return $this;
    }

    public function getPlaceholderEn(): ?string
    {
        return $this->placeholderEn;
    }

    public function setPlaceholderEn(?string $placeholderEn): static
    {
        $this->placeholderEn = $placeholderEn;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getOptions(): ?string
    {
        return $this->options;
    }

    public function setOptions(?string $options): static
    {
        $this->options = $options;

        return $this;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): static
    {
        $this->sortOrder = $sortOrder;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isMandatory(): bool
    {
        return $this->isMandatory;
    }

    public function setIsMandatory(bool $isMandatory): static
    {
        $this->isMandatory = $isMandatory;

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
}
