<?php

namespace App\Personnalite\Entity;

use App\Personnalite\Repository\PersonalityAnswerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PersonalityAnswerRepository::class)]
class PersonalityAnswer
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'answers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PersonalityQuestion $question = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $text = null;

    // Protégé : « value » est un mot clé de MariaDB.
    #[ORM\Column(name: '`value`', length: 255)]
    private ?string $value = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $sortOrder = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestion(): ?PersonalityQuestion
    {
        return $this->question;
    }

    public function setQuestion(?PersonalityQuestion $question): static
    {
        $this->question = $question;

        return $this;
    }

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): static
    {
        $this->text = $text;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

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
}
