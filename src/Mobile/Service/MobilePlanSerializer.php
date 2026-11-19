<?php

namespace App\Mobile\Service;

use App\Abonnement\Service\Entitlements;
use App\Bilan\Service\Seasons;
use App\Compte\Entity\User;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDHygiene\Entity\DailyLog;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Entity\StewardshipRhythm;
use App\PlanDIntendance\Service\Stewardship;
use App\PlanDeVie\Entity\Season;
use App\PlanDeVie\Service\PlanCatalog;
use App\PlanDeVie\Service\PlanProgress;
use App\Shared\Twig\LexiconExtension;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sérialiseur complet des 6 plans de KIINI SAWA pour l'application mobile.
 * Garantit des libellés 100 % français (zéro code brut, respect strict de la Charte).
 */
final class MobilePlanSerializer
{
    private const PLAN_ICONS = [
        'VIE' => 'compass',
        'CROISSANCE' => 'sprout',
        'DEVELOPPEMENT' => 'wrench',
        'HYGIENE' => 'heart-pulse',
        'INTENDANCE' => 'hand-coins',
        'TRANSMISSION' => 'hand-heart',
    ];

    private const PRIORITIES = [
        'HIGH' => 'Haute',
        'MEDIUM' => 'Moyenne',
        'LOW' => 'Basse',
    ];

    private const ACTION_STATUSES = [
        'NOT_STARTED' => 'À commencer',
        'IN_PROGRESS' => 'En cours',
        'COMPLETED' => 'Terminé',
        'DONE' => 'Terminé',
    ];

    private const CONFIRM_STATUSES = [
        'PENDING' => 'En attente',
        'CONFIRMED' => 'Confirmé',
        'REJECTED' => 'Écarté',
    ];

    private const CHARACTER_ACTIONS = [
        'DEVELOP' => 'Développer',
        'STRENGTHEN' => 'Renforcer',
        'ABANDON' => 'Abandonner',
    ];

    private const SKILL_CATEGORIES = [
        'TECHNICAL' => 'Technique & Métier',
        'INTERPERSONAL' => 'Relationnelle',
        'COGNITIVE' => 'Cognitive',
        'EMOTIONAL' => 'Émotionnelle',
        'FINANCIAL' => 'Financière',
        'SOCIAL' => 'Sociale',
        'PHYSICAL' => 'Physique',
    ];

    private const RESOURCE_TYPES = [
        'BOOK' => 'Livre',
        'COURSE' => 'Formation',
        'MENTOR' => 'Mentorat',
        'PRACTICE' => 'Pratique',
        'ARTICLE' => 'Article',
        'VIDEO' => 'Audio / Vidéo',
        'OTHER' => 'Autre',
    ];

    public function __construct(
        private readonly PlanProgress $planProgress,
        private readonly Entitlements $entitlements,
        private readonly EntityManagerInterface $em,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOverview(User $user): array
    {
        $progressMap = $this->planProgress->forUser($user);
        $locked = $this->entitlements->lockedPlans($user);

        $plans = [];
        $totalPercent = 0;
        $completedPlans = 0;

        foreach (PlanCatalog::PLANS as $key => $meta) {
            $prog = $progressMap[$key] ?? ['percent' => 0, 'done' => 0, 'total' => 0, 'steps' => [], 'next' => null];
            $isLocked = \in_array($key, $locked, true);
            $totalPercent += $prog['percent'];
            if ($prog['percent'] >= 100) {
                ++$completedPlans;
            }

            $steps = array_map(fn (array $s): array => [
                'label' => $s['label'],
                'done' => $s['done'],
            ], $prog['steps']);

            $plans[] = [
                'key' => $key,
                'label' => $meta['label'],
                'question' => $meta['question'],
                'desc' => $meta['desc'],
                'icon' => self::PLAN_ICONS[$key] ?? 'compass',
                'locked' => $isLocked,
                'webPath' => $this->urls->generate($meta['route']),
                'progress' => [
                    'percent' => $prog['percent'],
                    'done' => $prog['done'],
                    'total' => $prog['total'],
                    'steps' => $steps,
                    'nextStepLabel' => $prog['next']['label'] ?? null,
                ],
            ];
        }

        return [
            'overallPercent' => (int) round($totalPercent / max(1, \count(PlanCatalog::PLANS))),
            'completedPlansCount' => $completedPlans,
            'totalPlansCount' => \count(PlanCatalog::PLANS),
            'plans' => $plans,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializePlan(User $user, string $key): array
    {
        $upperKey = strtoupper($key);
        $catalog = PlanCatalog::PLANS[$upperKey] ?? null;
        if (null === $catalog) {
            throw new \InvalidArgumentException(sprintf('Plan inconnu : %s', $key));
        }

        $progressMap = $this->planProgress->forUser($user);
        $prog = $progressMap[$upperKey] ?? ['percent' => 0, 'done' => 0, 'total' => 0, 'steps' => [], 'next' => null];
        $isLocked = \in_array($upperKey, $this->entitlements->lockedPlans($user), true);

        $content = match ($upperKey) {
            'VIE' => $this->serializeVie($user),
            'CROISSANCE' => $this->serializeCroissance($user),
            'DEVELOPPEMENT' => $this->serializeDeveloppement($user),
            'HYGIENE' => $this->serializeHygiene($user),
            'INTENDANCE' => $this->serializeIntendance($user),
            'TRANSMISSION' => $this->serializeTransmission($user),
            default => [],
        };

        return [
            'key' => $upperKey,
            'label' => $catalog['label'],
            'question' => $catalog['question'],
            'desc' => $catalog['desc'],
            'icon' => self::PLAN_ICONS[$upperKey] ?? 'compass',
            'locked' => $isLocked,
            'webPath' => $this->urls->generate($catalog['route']),
            'progress' => [
                'percent' => $prog['percent'],
                'done' => $prog['done'],
                'total' => $prog['total'],
                'steps' => array_map(fn (array $s): array => ['label' => $s['label'], 'done' => $s['done']], $prog['steps']),
                'nextStepLabel' => $prog['next']['label'] ?? null,
            ],
            'content' => $content,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVie(User $user): array
    {
        $vie = $user->getPlanDeVie();
        $purpose = $vie?->getPurpose();
        $vision = $vie?->getVision();

        $purposeData = null;
        if (null !== $purpose && '' !== trim((string) $purpose->getStatement())) {
            $purposeData = [
                'statement' => $purpose->getStatement(),
                'longStatement' => $purpose->getLongStatement(),
                'hypotheses' => $purpose->getHypotheses(),
                'detail' => $purpose->getDetail(),
                'proofs' => $purpose->getProofs(),
            ];
        }

        $values = [];
        foreach ($vie?->getValues() ?? [] as $val) {
            $values[] = [
                'id' => $val->getId(),
                'name' => $val->getName(),
                'behavior' => $val->getBehavior(),
                'category' => $val->getCategory(),
            ];
        }

        $passions = [];
        foreach ($vie?->getPassions() ?? [] as $pas) {
            $passions[] = [
                'id' => $pas->getId(),
                'name' => $pas->getName(),
                'context' => $pas->getContext(),
            ];
        }

        $visionData = null;
        if (null !== $vision && '' !== trim((string) $vision->getContent())) {
            $visionData = [
                'content' => $vision->getContent(),
                'horizon' => $vision->getHorizon(),
                'beneficiaries' => $vision->getBeneficiaries(),
                'indicators' => $vision->getIndicators(),
            ];
        }

        $goals = [];
        foreach ($vie?->getLifeGoals() ?? [] as $g) {
            $milestones = [];
            foreach ($g->getMilestones() as $m) {
                $milestones[] = [
                    'id' => $m->getId(),
                    'title' => $m->getTitle(),
                    'dueDate' => $m->getDueDate()?->format('Y-m-d'),
                    'completed' => $m->isCompleted(),
                ];
            }

            $cat = (string) $g->getCategory();
            $priority = (string) $g->getPriority();

            $goals[] = [
                'id' => $g->getId(),
                'title' => $g->getTitle(),
                'category' => $cat,
                'categoryLabel' => LexiconExtension::GOAL_CATEGORIES[strtoupper($cat)] ?? $cat,
                'priority' => $priority,
                'priorityLabel' => self::PRIORITIES[strtoupper($priority)] ?? $priority,
                'deadline' => $g->getDeadline()?->format('Y-m-d'),
                'metric' => $g->getMetric(),
                'progress' => $g->getProgress(),
                'milestones' => $milestones,
            ];
        }

        $latestSeason = $this->em->getRepository(Season::class)->findOneBy(['user' => $user], ['startDate' => 'DESC', 'id' => 'DESC']);
        $seasonData = null;
        if (null !== $latestSeason) {
            $kind = (string) $latestSeason->getKind();
            $meta = Seasons::KINDS[$kind] ?? null;
            $seasonData = [
                'kind' => $kind,
                'kindLabel' => $meta['label'] ?? $kind,
                'what' => $meta['what'] ?? null,
                'work' => $meta['work'] ?? null,
                'trap' => $meta['trap'] ?? null,
                'theme' => $latestSeason->getTheme(),
                'startDate' => $latestSeason->getStartDate()?->format('Y-m-d'),
                'endDate' => $latestSeason->getEndDate()?->format('Y-m-d'),
                'reviewNote' => $latestSeason->getReviewNote(),
            ];
        }

        return [
            'purpose' => $purposeData,
            'values' => $values,
            'passions' => $passions,
            'vision' => $visionData,
            'goals' => $goals,
            'currentSeason' => $seasonData,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCroissance(User $user): array
    {
        $croissance = $user->getPlanDeCroissance();
        $growthGoals = $croissance?->getGrowthGoals()->toArray() ?? [];

        $charGoals = array_filter($growthGoals, fn ($g) => GrowthAxis::CHARACTER === $g->getAxis());
        $relGoals = array_filter($growthGoals, fn ($g) => GrowthAxis::RELATION === $g->getAxis());

        $serializeGoal = function ($g): array {
            $actions = [];
            foreach ($g->getActions() as $a) {
                $status = (string) $a->getStatus();
                $actions[] = [
                    'id' => $a->getId(),
                    'title' => $a->getTitle(),
                    'status' => $status,
                    'statusLabel' => self::ACTION_STATUSES[$status] ?? $status,
                    'deadline' => $a->getDeadline()?->format('Y-m-d'),
                ];
            }

            $charAction = $g->getCharacterAction()?->value;

            $supported = [];
            foreach ($g->getSupportedLifeGoals() as $lg) {
                $supported[] = ['id' => $lg->getId(), 'title' => $lg->getTitle()];
            }

            return [
                'id' => $g->getId(),
                'title' => $g->getTitle(),
                'characterAction' => $charAction,
                'characterActionLabel' => null !== $charAction ? (self::CHARACTER_ACTIONS[$charAction] ?? $charAction) : null,
                'strategy' => $g->getStrategy(),
                'why' => $g->getWhy(),
                'metric' => $g->getMetric(),
                'progress' => $g->getProgress(),
                'deadline' => $g->getDeadline()?->format('Y-m-d'),
                'supportedLifeGoals' => $supported,
                'actions' => $actions,
            ];
        };

        $principles = [];
        foreach ($this->em->getRepository(\App\PlanDeCroissance\Entity\Principle::class)->findBy(['user' => $user], ['createdAt' => 'ASC']) as $p) {
            $pillar = $p->getPillar();
            $principles[] = [
                'id' => $p->getId(),
                'title' => $p->getTitle(),
                'description' => $p->getDescription(),
                'pillar' => $pillar,
                'pillarLabel' => null !== $pillar && isset(PlanCatalog::PLANS[$pillar]) ? PlanCatalog::PLANS[$pillar]['label'] : $pillar,
            ];
        }

        $confirmPoints = [];
        foreach ($croissance?->getConfirmPoints() ?? [] as $cp) {
            $status = (string) $cp->getStatus();
            $confirmPoints[] = [
                'id' => $cp->getId(),
                'statement' => $cp->getStatement(),
                'status' => $status,
                'statusLabel' => self::CONFIRM_STATUSES[$status] ?? $status,
                'evidencesCount' => $cp->getEvidences()->count(),
            ];
        }

        return [
            'characterGoals' => array_values(array_map($serializeGoal, $charGoals)),
            'relationGoals' => array_values(array_map($serializeGoal, $relGoals)),
            'principles' => $principles,
            'confirmPoints' => $confirmPoints,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDeveloppement(User $user): array
    {
        $dev = $user->getPlanDeDeveloppementPersonnel();
        $growthGoals = $user->getPlanDeCroissance()?->getGrowthGoals()->toArray() ?? [];
        $compGoals = array_filter($growthGoals, fn ($g) => GrowthAxis::COMPETENCE === $g->getAxis());

        $competenceGoals = [];
        foreach ($compGoals as $g) {
            $supported = [];
            foreach ($g->getSupportedLifeGoals() as $lg) {
                $supported[] = ['id' => $lg->getId(), 'title' => $lg->getTitle()];
            }
            $competenceGoals[] = [
                'id' => $g->getId(),
                'title' => $g->getTitle(),
                'strategy' => $g->getStrategy(),
                'progress' => $g->getProgress(),
                'deadline' => $g->getDeadline()?->format('Y-m-d'),
                'supportedLifeGoals' => $supported,
            ];
        }

        $skillGoals = [];
        foreach ($dev?->getSkillGoals() ?? [] as $sg) {
            $resources = [];
            foreach ($sg->getLearningResources() as $r) {
                $type = (string) $r->getType();
                $status = (string) $r->getStatus();
                $resources[] = [
                    'id' => $r->getId(),
                    'title' => $r->getTitle(),
                    'type' => $type,
                    'typeLabel' => self::RESOURCE_TYPES[$type] ?? $type,
                    'author' => $r->getAuthor(),
                    'url' => $r->getUrl(),
                    'status' => $status,
                    'statusLabel' => self::ACTION_STATUSES[$status] ?? $status,
                    'progress' => $r->getProgress(),
                    'rating' => $r->getRating(),
                    'notes' => $r->getNotes(),
                ];
            }

            $cat = $sg->getCategory()->value;
            $supported = [];
            foreach ($sg->getSupportedLifeGoals() as $lg) {
                $supported[] = ['id' => $lg->getId(), 'title' => $lg->getTitle()];
            }

            $skillGoals[] = [
                'id' => $sg->getId(),
                'title' => $sg->getTitle(),
                'category' => $cat,
                'categoryLabel' => self::SKILL_CATEGORIES[$cat] ?? $cat,
                'targetLevel' => $sg->getTargetLevel(),
                'progress' => $sg->getProgress(),
                'deadline' => $sg->getDeadline()?->format('Y-m-d'),
                'supportedLifeGoals' => $supported,
                'resources' => $resources,
            ];
        }

        $careerSteps = [];
        foreach ($dev?->getCareerSteps() ?? [] as $cs) {
            $status = (string) $cs->getStatus();
            $careerSteps[] = [
                'id' => $cs->getId(),
                'title' => $cs->getTitle(),
                'company' => $cs->getCompany(),
                'status' => $status,
                'statusLabel' => self::ACTION_STATUSES[$status] ?? $status,
                'deadline' => $cs->getDeadline()?->format('Y-m-d'),
                'description' => $cs->getDescription(),
            ];
        }

        return [
            'competenceGoals' => array_values($competenceGoals),
            'skillGoals' => $skillGoals,
            'careerSteps' => $careerSteps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHygiene(User $user): array
    {
        $hygiene = $user->getPlanDHygiene();
        $domains = [];

        foreach ($hygiene?->getHygieneDomains() ?? [] as $d) {
            $supported = [];
            foreach ($d->getSupportedLifeGoals() as $lg) {
                $supported[] = ['id' => $lg->getId(), 'title' => $lg->getTitle()];
            }
            $domains[] = [
                'id' => $d->getId(),
                'name' => $d->getName(),
                'parentDomain' => $d->getParentDomain(),
                'description' => $d->getDescription(),
                'targetScore' => $d->getTargetScore(),
                'habitsCount' => $d->getHabits()->count(),
                'supportedLifeGoals' => $supported,
            ];
        }

        $logs = [];
        if (null !== $hygiene) {
            $recent = $this->em->getRepository(DailyLog::class)->findBy(
                ['planDHygiene' => $hygiene],
                ['date' => 'DESC', 'id' => 'DESC'],
                7
            );
            foreach ($recent as $l) {
                $logs[] = [
                    'date' => $l->getDate()?->format('Y-m-d'),
                    'intention' => $l->getIntention(),
                    'intentionKept' => $l->isIntentionKept(),
                    'energyLevel' => $l->getEnergyLevel(),
                    'moodLevel' => $l->getMoodLevel(),
                    'stressLevel' => $l->getStressLevel(),
                    'sleepHours' => $l->getSleepHours(),
                    'gratitude' => $l->getGratitude(),
                ];
            }
        }

        return [
            'domains' => $domains,
            'recentLogs' => $logs,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIntendance(User $user): array
    {
        $rhythm = $this->em->getRepository(StewardshipRhythm::class)->findOneBy(['user' => $user]);
        $rhythmData = null;
        if (null !== $rhythm) {
            $freq = $rhythm->getFrequency();
            $rhythmData = [
                'frequency' => $freq,
                'frequencyLabel' => StewardshipRhythm::FREQUENCIES[$freq] ?? $freq,
                'withWhom' => $rhythm->getWithWhom(),
                'lastReviewAt' => $rhythm->getLastReviewAt()?->format('Y-m-d'),
                'nextReviewAt' => $rhythm->nextReviewAt()?->format('Y-m-d'),
            ];
        }

        $items = $this->em->getRepository(StewardshipItem::class)->findBy(
            ['user' => $user],
            ['priority' => 'DESC', 'position' => 'ASC', 'id' => 'ASC']
        );

        $totalCount = \count($items);
        $priorityCount = 0;
        $fructifieCount = 0;
        $dortCount = 0;
        $fuitCount = 0;

        $serializedItems = [];
        foreach ($items as $item) {
            if ($item->isPriority()) {
                ++$priorityCount;
            }
            $status = $item->getStatus();
            if ('FRUCTIFIE' === $status) {
                ++$fructifieCount;
            } elseif ('DORT' === $status) {
                ++$dortCount;
            } elseif ('FUIT' === $status) {
                ++$fuitCount;
            }

            $decision = $item->getDecision();
            $origin = $item->getOrigin();

            $serializedItems[] = [
                'id' => $item->getId(),
                'origin' => $origin,
                'originLabel' => Stewardship::originLabel($origin),
                'label' => $item->getLabel(),
                'note' => $item->getNote(),
                'status' => $status,
                'statusLabel' => null !== $status && isset(Stewardship::STATUSES[$status]) ? Stewardship::STATUSES[$status]['label'] : null,
                'decision' => $decision,
                'decisionLabel' => null !== $decision && isset(Stewardship::DECISIONS[$decision]) ? Stewardship::DECISIONS[$decision]['label'] : null,
                'decisionHelp' => null !== $decision && isset(Stewardship::DECISIONS[$decision]) ? Stewardship::DECISIONS[$decision]['help'] : null,
                'actionText' => $item->getActionText(),
                'actionDoneAt' => $item->getActionDoneAt()?->format('Y-m-d'),
                'priority' => $item->isPriority(),
                'lifeGoalTitle' => $item->getLifeGoal()?->getTitle(),
            ];
        }

        return [
            'rhythm' => $rhythmData,
            'summary' => [
                'totalCount' => $totalCount,
                'priorityCount' => $priorityCount,
                'fructifieCount' => $fructifieCount,
                'dortCount' => $dortCount,
                'fuitCount' => $fuitCount,
            ],
            'items' => $serializedItems,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransmission(User $user): array
    {
        $transmission = $user->getPlanDeTransmission();

        $legacyGoals = [];
        foreach ($transmission?->getLegacyGoals() ?? [] as $lg) {
            $actions = [];
            foreach ($lg->getTransmissionActions() as $a) {
                $status = (string) $a->getStatus();
                $actions[] = [
                    'id' => $a->getId(),
                    'title' => $a->getTitle(),
                    'status' => $status,
                    'statusLabel' => self::ACTION_STATUSES[$status] ?? $status,
                    'deadline' => $a->getDeadline()?->format('Y-m-d'),
                ];
            }

            $supported = [];
            foreach ($lg->getSupportedLifeGoals() as $gl) {
                $supported[] = ['id' => $gl->getId(), 'title' => $gl->getTitle()];
            }

            $legacyGoals[] = [
                'id' => $lg->getId(),
                'title' => $lg->getTitle(),
                'targetBeneficiary' => $lg->getTargetBeneficiary(),
                'description' => $lg->getDescription(),
                'progress' => $lg->getProgress(),
                'deadline' => $lg->getDeadline()?->format('Y-m-d'),
                'supportedLifeGoals' => $supported,
                'actions' => $actions,
            ];
        }

        $talentDons = [];
        foreach ($transmission?->getTalentDons() ?? [] as $td) {
            $talentDons[] = [
                'id' => $td->getId(),
                'text' => $td->getText(),
            ];
        }

        return [
            'eternityPhrase' => $transmission?->getEternityPhrase(),
            'treasureShift' => $transmission?->getTreasureShift(),
            'legacyGoals' => $legacyGoals,
            'talentDons' => $talentDons,
        ];
    }
}
