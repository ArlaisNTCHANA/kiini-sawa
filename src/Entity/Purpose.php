<?php

namespace App\Entity;

use App\Repository\PurposeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurposeRepository::class)]
class Purpose
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'purpose', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?PlanDeVie $planDeVie = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $statement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $longStatement = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $hypotheses = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $detail = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $proofs = null;

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

    public function getStatement(): ?string
    {
        return $this->statement;
    }

    public function setStatement(string $statement): static
    {
        $this->statement = $statement;

        return $this;
    }

    public function getLongStatement(): ?string
    {
        return $this->longStatement;
    }

    public function setLongStatement(?string $longStatement): static
    {
        $this->longStatement = $longStatement;

        return $this;
    }

    public function getHypotheses(): ?string
    {
        return $this->hypotheses;
    }

    public function setHypotheses(?string $hypotheses): static
    {
        $this->hypotheses = $hypotheses;

        return $this;
    }

    public function getDetail(): ?string
    {
        return $this->detail;
    }

    public function setDetail(?string $detail): static
    {
        $this->detail = $detail;

        return $this;
    }

    public function getProofs(): ?string
    {
        return $this->proofs;
    }

    public function setProofs(?string $proofs): static
    {
        $this->proofs = $proofs;

        return $this;
    }
}
