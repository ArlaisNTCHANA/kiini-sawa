<?php

namespace App\Academie\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Question de discernement : une mise en situation, 2 à 5 réponses, une ou plusieurs justes, et une explication.
 * Jamais de note ni de blocage : l'apprenant voit l'explication et peut recommencer.
 */
#[ORM\Entity]
#[ORM\Table(name: 'academy_question')]
class LessonQuestion
{
    public const MAX_PER_LESSON = 5;
    public const MAX_OPTIONS = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Lesson $lesson = null;

    #[ORM\Column]
    private int $sortOrder = 0;

    #[ORM\Column(type: Types::TEXT)]
    private string $question = '';

    /** @var list<array{id: string, label: string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $correctOptionIds = [];

    #[ORM\Column(type: Types::TEXT)]
    private string $explanation = '';

    public function getId(): ?int { return $this->id; }
    public function getLesson(): ?Lesson { return $this->lesson; }
    public function setLesson(?Lesson $lesson): static { $this->lesson = $lesson; return $this; }
    public function getSortOrder(): int { return $this->sortOrder; }
    public function setSortOrder(int $sortOrder): static { $this->sortOrder = $sortOrder; return $this; }
    public function getQuestion(): string { return $this->question; }
    public function setQuestion(string $question): static { $this->question = $question; return $this; }

    /** @return list<array{id: string, label: string}> */
    public function getOptions(): array { return $this->options; }

    /** @return list<string> */
    public function getCorrectOptionIds(): array { return $this->correctOptionIds; }

    /**
     * Réponses dans l'ordre, avec un identifiant stable (a, b, c…) et les bonnes réponses.
     *
     * @param list<string> $labels
     * @param list<int>    $correctIndexes positions (0, 1…) des bonnes réponses dans $labels
     */
    public function setAnswers(array $labels, array $correctIndexes): static
    {
        $this->options = [];
        $this->correctOptionIds = [];
        foreach (array_values($labels) as $i => $label) {
            $id = \chr(97 + $i);
            $this->options[] = ['id' => $id, 'label' => $label];
            if (\in_array($i, $correctIndexes, true)) {
                $this->correctOptionIds[] = $id;
            }
        }

        return $this;
    }

    /** Plusieurs bonnes réponses : l'apprenant coche des cases au lieu d'un seul choix. */
    public function isMultiple(): bool { return \count($this->correctOptionIds) > 1; }
    public function isCorrect(string $optionId): bool { return \in_array($optionId, $this->correctOptionIds, true); }
    public function getExplanation(): string { return $this->explanation; }
    public function setExplanation(string $explanation): static { $this->explanation = $explanation; return $this; }
}
