<?php

namespace App\Boussole\Entity;

use App\Boussole\Repository\BoussoleResultRepository;
use App\Boussole\Service\BoussoleEngine;
use App\Compte\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une passation de la Boussole 360 enregistrée dans le compte (historique : une ligne par passation).
 *
 * On garde les 24 réponses brutes : la lecture complète (textes, pistes) est recalculée à l'affichage par
 * BoussoleEngine. Les scores, le score de cohérence et les plans fort et prioritaire sont figés à
 * l'enregistrement pour l'historique.
 */
#[ORM\Entity(repositoryClass: BoussoleResultRepository::class)]
#[ORM\Index(name: 'idx_boussole_user_date', columns: ['user_id', 'created_at'])]
class BoussoleResult
{
    public const SOURCE_WEB = 'web';
    public const SOURCE_MOBILE = 'mobile';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** @var list<int> */
    #[ORM\Column(type: Types::JSON)]
    private array $answers;

    /** @var array<string, int> */
    #[ORM\Column(type: Types::JSON)]
    private array $scores;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $coherence;

    #[ORM\Column(length: 20)]
    private string $strongestKey;

    #[ORM\Column(length: 20)]
    private string $priorityKey;

    #[ORM\Column(length: 10)]
    private string $source;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @param list<int>            $answers
     * @param array<string, mixed> $result  résultat de BoussoleEngine::evaluate()
     */
    public function __construct(User $user, array $answers, array $result, string $source, ?\DateTimeImmutable $createdAt = null)
    {
        $this->user = $user;
        $this->answers = $answers;
        $this->scores = $result['scores'];
        $this->coherence = $result['coherence'];
        $this->strongestKey = $result['strongestKey'];
        $this->priorityKey = $result['priorityKey'];
        $this->source = $source;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /** @return list<int> */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    /** @return array<string, int> dans l'ordre des plans (MySQL réordonne les clés des colonnes JSON) */
    public function getScores(): array
    {
        $ordered = [];
        foreach (BoussoleEngine::PLAN_ORDER as $key) {
            $ordered[$key] = (int) ($this->scores[$key] ?? 0);
        }

        return $ordered;
    }

    public function getCoherence(): int
    {
        return $this->coherence;
    }

    public function getStrongestKey(): string
    {
        return $this->strongestKey;
    }

    public function getPriorityKey(): string
    {
        return $this->priorityKey;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
