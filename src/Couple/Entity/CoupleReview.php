<?php

namespace App\Couple\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Revue de couple mensuelle, faite ensemble : gratitude, ce qui va bien, à améliorer, décision commune.
 */
#[ORM\Entity]
#[ORM\UniqueConstraint(name: 'uniq_couple_review_month', columns: ['couple_id', 'month'])]
class CoupleReview
{
    public const QUESTIONS = [
        'gratitude' => ['Ce dont nous sommes reconnaissants', 'Un moment, une attention, une grâce reçue ce mois-ci.'],
        'good' => ['Ce qui va bien entre nous', 'Ce que nous voulons garder et cultiver.'],
        'improve' => ['Ce que nous voulons améliorer', 'Avec bienveillance : une chose à la fois.'],
        'decision' => ['Notre décision pour le mois prochain', 'Une action concrète, ensemble.'],
        'prayer' => ['Nos sujets de prière', 'Ce que nous remettons à Dieu ensemble.'],
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Couple $couple;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $month;

    #[ORM\Column(type: Types::JSON)]
    private array $answers = [];

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Couple $couple, \DateTimeImmutable $month)
    {
        $this->couple = $couple;
        $this->month = $month;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getCouple(): Couple { return $this->couple; }
    public function getMonth(): \DateTimeImmutable { return $this->month; }
    public function getAnswers(): array { return $this->answers; }
    public function setAnswers(array $answers): static { $this->answers = $answers; $this->updatedAt = new \DateTimeImmutable(); return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
