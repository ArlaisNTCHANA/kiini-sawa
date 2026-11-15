<?php

namespace App\Entity;

use App\Repository\VisionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VisionRepository::class)]
class Vision
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'vision', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanDeVie $planDeVie = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 255)]
    private ?string $horizon = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $beneficiaries = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $indicators = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $images = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPlanDeVie(): ?PlanDeVie
    {
        return $this->planDeVie;
    }

    public function setPlanDeVie(PlanDeVie $planDeVie): static
    {
        $this->planDeVie = $planDeVie;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getHorizon(): ?string
    {
        return $this->horizon;
    }

    public function setHorizon(string $horizon): static
    {
        $this->horizon = $horizon;

        return $this;
    }

    public function getBeneficiaries(): ?string
    {
        return $this->beneficiaries;
    }

    public function setBeneficiaries(string $beneficiaries): static
    {
        $this->beneficiaries = $beneficiaries;

        return $this;
    }

    public function getIndicators(): ?string
    {
        return $this->indicators;
    }

    public function setIndicators(string $indicators): static
    {
        $this->indicators = $indicators;

        return $this;
    }

    public function getImages(): ?string
    {
        return $this->images;
    }

    public function setImages(?string $images): static
    {
        $this->images = $images;

        return $this;
    }
}
