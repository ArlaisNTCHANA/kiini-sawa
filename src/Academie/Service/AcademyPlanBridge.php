<?php

namespace App\Academie\Service;

use App\Abonnement\Service\Entitlements;
use App\Academie\Entity\Lesson;
use App\Academie\Entity\LessonCompletion;
use App\Academie\Entity\LessonNote;
use App\Academie\Exception\PracticeRefused;
use App\Compte\Entity\User;
use App\Couple\Entity\CoupleItem;
use App\Couple\Service\CoupleService;
use App\Journal\Entity\ReflectionNote;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\PlanDeCroissance;
use App\PlanDeCroissance\Entity\Principle;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDeTransmission\Entity\LegacyGoal;
use App\PlanDeTransmission\Entity\PlanDeTransmission;
use App\PlanDeTransmission\Entity\TransmissionAction;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Entity\Value;
use App\PlanDHygiene\Entity\HygieneDomain;
use App\PlanDHygiene\Entity\PlanDHygiene;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Service\Stewardship;
use App\Rituels\Entity\Habit;
use App\Rituels\Enum\HabitFrequency;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Le pont entre une leçon et les vrais plans du membre : « Mettre en pratique » ajoute un élément dans un des 6 plans,
 * les Rituels, le Journal ou le Sanctuaire du Couple, seulement quand il clique sur le bouton d'accord.
 *
 * - Sans doublon : refaire l'exercice met à jour l'élément déjà créé (retrouvé par son type et son identifiant, et
 *   seulement s'il appartient bien au membre). S'il l'a supprimé entre-temps, un nouvel élément est créé.
 * - Mêmes droits que le reste de l'application : un plan réservé à PRO, la limite de rituels de l'offre gratuite, un
 *   Sanctuaire du Couple ouvert. Exception voulue par le fondateur : écrire dans le Journal depuis une leçon est permis
 *   à tous.
 * - L'Académie ajoute, elle ne remplace jamais (ni raison d'être ni Vision).
 */
final class AcademyPlanBridge
{
    /** Repère des éléments créés par l'Académie dans le Plan de Croissance (comme « TEST » pour les rapports de test). */
    public const SOURCE = 'ACADEMIE';

    /**
     * Destinations proposées quand la leçon n'a pas d'exercice prévu par l'auteur : le membre note un point à
     * travailler et choisit où l'envoyer.
     */
    public const FREE_DESTINATIONS = [
        'VIE|add_life_goal', 'CROISSANCE|add_growth_goal', 'DEVELOPPEMENT|add_skill_goal', 'HYGIENE|add_hygiene_domain',
        'INTENDANCE|add_stewardship_item', 'TRANSMISSION|add_legacy_goal', 'RITUEL|add_habit',
    ];

    private const LIFE_GOAL_CATEGORIES = ['BE' => 'Être', 'DO' => 'Faire', 'HAVE' => 'Avoir', 'GIVE' => 'Donner'];
    private const HABIT_FREQUENCIES = ['DAILY' => 'Chaque jour', 'WEEKLY' => 'Chaque semaine'];

    /** Type enregistré => classe de l'entité. */
    private const CLASSES = [
        'Value' => Value::class, 'LifeGoal' => LifeGoal::class, 'GrowthGoal' => GrowthGoal::class, 'Principle' => Principle::class,
        'HygieneDomain' => HygieneDomain::class, 'StewardshipItem' => StewardshipItem::class, 'LegacyGoal' => LegacyGoal::class,
        'TransmissionAction' => TransmissionAction::class, 'Habit' => Habit::class, 'ReflectionNote' => ReflectionNote::class,
        'CoupleItem' => CoupleItem::class,
    ];

    /** Où voir le résultat (route), par cible. */
    private const ROUTES = [
        'VIE' => 'app_plan_de_vie', 'CROISSANCE' => 'app_plan_de_croissance', 'DEVELOPPEMENT' => 'app_plan_de_developpement',
        'HYGIENE' => 'app_plan_d_hygiene', 'INTENDANCE' => 'app_plan_d_intendance', 'TRANSMISSION' => 'app_plan_de_transmission',
        'RITUEL' => 'app_user_habits', 'JOURNAL' => 'app_journal', 'COUPLE' => 'app_couple',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Entitlements $entitlements,
        private readonly CoupleService $couples,
        private readonly AcademyJournal $journal,
    ) {
    }

    public static function route(string $target): ?string
    {
        return self::ROUTES[$target] ?? null;
    }

    /** Pourquoi ce membre ne peut pas écrire là (message à afficher), ou null s'il le peut. */
    public function blocker(User $user, string $target, string $action, bool $creating = true): ?string
    {
        if (!PracticeCatalog::isValid($target, $action)) {
            return 'Cet exercice n\'est plus disponible.';
        }
        $feature = Entitlements::PLAN_FEATURES[$target] ?? null;
        if (null !== $feature && !$this->entitlements->can($user, $feature)) {
            return 'Le '.PracticeCatalog::TARGETS[$target]['label'].' fait partie de KIINI SAWA PRO. Vous pouvez terminer la leçon sans modifier votre plan.';
        }
        if ('RITUEL' === $target) {
            if (!$this->entitlements->can($user, 'habits')) {
                return 'Les Rituels font partie de KIINI SAWA PRO. Vous pouvez terminer la leçon sans modifier vos Rituels.';
            }
            $limit = $this->entitlements->limit($user, 'habits');
            if ($creating && null !== $limit && $user->getHabits()->count() >= $limit) {
                return 'Vous avez atteint la limite de rituels de l\'offre gratuite. Vous pouvez terminer la leçon sans en ajouter.';
            }
        }
        if ('COUPLE' === $target) {
            $couple = $this->couples->coupleOf($user);
            if (null === $couple || !$this->couples->canUse($couple)) {
                return 'Le Sanctuaire du Couple s\'ouvre quand vos deux comptes sont reliés et PRO. Vous pouvez terminer la leçon sans rien y ajouter.';
            }
        }
        if ('add_transmission_action' === $action && null === $this->choices($user, $action)) {
            return 'Votre Plan de Transmission n\'a pas encore de legs : ajoutez-en un d\'abord, puis revenez à cette leçon.';
        }

        return null;
    }

    /**
     * Ce que le formulaire demande pour cette action, en plus du texte principal.
     *
     * @return array{title: string, detail: ?string, choice: ?array{label: string, options: array<string, string>, default: string}}
     */
    public function form(User $user, string $action): array
    {
        $title = match ($action) {
            'add_value' => 'La valeur',
            'add_principle' => 'Le principe',
            'add_hygiene_domain' => 'Le domaine à soigner',
            'add_stewardship_item' => 'Ce que vous avez à gérer',
            'add_legacy_goal' => 'Ce que vous voulez transmettre',
            'add_transmission_action' => 'L\'action de transmission',
            'add_habit' => 'Le rituel',
            'add_note' => 'Votre note',
            'add_couple_item' => 'Ce que vous ajoutez',
            'add_skill_goal' => 'La compétence à développer',
            'add_learning_resource' => 'La connaissance à acquérir',
            default => 'Votre décision',
        };
        $detail = match ($action) {
            'add_value' => 'Comment elle se voit dans votre vie (facultatif)',
            'add_life_goal', 'add_transmission_action', 'add_note' => null,
            'add_habit' => 'Pourquoi ce rituel compte pour vous (facultatif)',
            default => 'Précisions (facultatif)',
        };
        $choice = $this->choices($user, $action);

        return ['title' => $title, 'detail' => $detail, 'choice' => $choice];
    }

    /** @return array{label: string, options: array<string, string>, default: string}|null */
    private function choices(User $user, string $action): ?array
    {
        return match ($action) {
            'add_life_goal' => ['label' => 'Dimension', 'options' => self::LIFE_GOAL_CATEGORIES, 'default' => 'DO'],
            'add_habit' => ['label' => 'Fréquence', 'options' => self::HABIT_FREQUENCIES, 'default' => 'DAILY'],
            'add_stewardship_item' => ['label' => 'Domaine', 'options' => array_map(fn (array $d) => $d['label'], Stewardship::DOMAINS), 'default' => 'FINANCES'],
            'add_couple_item' => ['label' => 'Type', 'options' => array_diff_key(CoupleItem::KINDS, ['INTENTION' => true]), 'default' => 'PROJECT'],
            'add_transmission_action' => $this->legacyChoices($user),
            default => null,
        };
    }

    /** @return array{label: string, options: array<string, string>, default: string}|null */
    private function legacyChoices(User $user): ?array
    {
        $options = [];
        foreach ($user->getPlanDeTransmission()?->getLegacyGoals() ?? [] as $goal) {
            $options[(string) $goal->getId()] = $goal->getTitle();
        }

        return $options ? ['label' => 'Le legs concerné', 'options' => $options, 'default' => (string) array_key_first($options)] : null;
    }

    /**
     * Enregistre l'exercice dans le plan (création, ou mise à jour de l'élément déjà créé par cette leçon).
     *
     * @return array{type: string, id: int}
     *
     * @throws PracticeRefused message à montrer au membre
     */
    public function save(User $user, Lesson $lesson, string $target, string $action, string $title, ?string $detail, ?string $choice, ?LessonCompletion $previous = null): array
    {
        $type = PracticeCatalog::TARGETS[$target]['actions'][$action]['entity'] ?? null;
        $existing = null;
        if (null !== $type && $previous?->getCreatedEntityType() === $type && $previous->getCreatedEntityId()) {
            $existing = $this->find($user, $type, $previous->getCreatedEntityId());
        }
        if (null !== $reason = $this->blocker($user, $target, $action, null === $existing)) {
            throw new PracticeRefused($reason);
        }
        $title = trim($title);
        $detail = null !== $detail && '' !== trim($detail) ? trim($detail) : null;
        if ('' === $title) {
            throw new PracticeRefused('Écrivez d\'abord votre réponse.');
        }
        $form = $this->form($user, $action);
        if (null !== $form['choice']) {
            $choice = isset($form['choice']['options'][(string) $choice]) ? (string) $choice : $form['choice']['default'];
        } else {
            $choice = null;
        }
        $short = mb_substr($title, 0, 255);
        $origin = 'Décidé dans la leçon « '.$lesson->getTitle().' » de l\'Académie.';

        $entity = $existing ?? $this->create($user, $lesson, $action, $choice);
        match ($action) {
            'add_value' => $entity->setName($short)->setBehavior($detail ?? ''),
            'add_life_goal' => $entity->setTitle($short)->setCategory($choice),
            'add_growth_goal', 'add_skill_goal', 'add_learning_resource' => $entity->setTitle($short)->setWhy($detail ?? $origin),
            'add_principle' => $entity->setTitle($short)->setDescription($detail),
            'add_hygiene_domain' => $entity->setName($short)->setDescription($detail),
            'add_stewardship_item' => $entity->setLabel($short)->setNote($detail)->setOrigin($choice),
            'add_legacy_goal' => $entity->setTitle($short)->setDescription($detail),
            'add_transmission_action' => $entity->setTitle($short),
            'add_habit' => $entity->setTitle($short)->setFrequency(HabitFrequency::from($choice))->setRootingIntention($detail ?? $origin),
            'add_note' => $entity->setContent(mb_substr($title, 0, 10000)),
            'add_couple_item' => $entity->setTitle($short)->setDetails($detail),
        };
        $this->em->flush();

        return ['type' => $type, 'id' => (int) $entity->getId()];
    }

    private function create(User $user, Lesson $lesson, string $action, ?string $choice): object
    {
        $entity = match ($action) {
            'add_value' => (new Value())->setPlanDeVie($this->planDeVie($user))->setCategory('real'),
            'add_life_goal' => (new LifeGoal())->setPlanDeVie($this->planDeVie($user)),
            'add_growth_goal' => $this->growthGoal($user, $lesson, GrowthAxis::CHARACTER, null),
            'add_skill_goal' => $this->growthGoal($user, $lesson, GrowthAxis::COMPETENCE, 'COMPETENCE'),
            'add_learning_resource' => $this->growthGoal($user, $lesson, GrowthAxis::COMPETENCE, 'CONNAISSANCE'),
            'add_principle' => (new Principle())->setUser($user)->setPillar('CROISSANCE'),
            'add_hygiene_domain' => (new HygieneDomain())->setPlanDHygiene($this->planDHygiene($user)),
            'add_stewardship_item' => (new StewardshipItem())->setUser($user),
            'add_legacy_goal' => (new LegacyGoal())->setPlanDeTransmission($this->planDeTransmission($user)),
            'add_transmission_action' => (new TransmissionAction())->setLegacyGoal($this->ownedLegacyGoal($user, (int) $choice)),
            'add_habit' => (new Habit())->setUser($user),
            'add_note' => $this->journal->newNote($user),
            'add_couple_item' => $this->couples->addItem($this->couples->coupleOf($user), $user, (string) $choice, ['title' => 'Nouvel élément']),
            default => throw new PracticeRefused('Cet exercice n\'est plus disponible.'),
        };
        $this->em->persist($entity);
        if ($entity instanceof ReflectionNote) {
            // La note de l'exercice apparaît aussi parmi les notes de la leçon
            $this->em->persist(new LessonNote($entity, $lesson, $lesson->getCourse()?->getPrimaryPlan()));
        }

        return $entity;
    }

    /** L'élément créé par une leçon, seulement s'il appartient bien à ce membre. */
    public function find(User $user, string $type, int $id): ?object
    {
        $class = self::CLASSES[$type] ?? null;
        $entity = null !== $class ? $this->em->find($class, $id) : null;
        if (null === $entity) {
            return null;
        }
        $owner = match (true) {
            $entity instanceof Value, $entity instanceof LifeGoal => [$entity->getPlanDeVie()?->getUser()],
            $entity instanceof GrowthGoal => [$entity->getPlanDeCroissance()?->getUser()],
            $entity instanceof HygieneDomain => [$entity->getPlanDHygiene()?->getUser()],
            $entity instanceof LegacyGoal => [$entity->getPlanDeTransmission()?->getUser()],
            $entity instanceof TransmissionAction => [$entity->getLegacyGoal()?->getPlanDeTransmission()?->getUser()],
            $entity instanceof CoupleItem => [$entity->getCouple()->getPartnerA(), $entity->getCouple()->getPartnerB()],
            default => [$entity->getUser()],
        };

        return \in_array($user, $owner, true) ? $entity : null;
    }

    private function growthGoal(User $user, Lesson $lesson, GrowthAxis $axis, ?string $type): GrowthGoal
    {
        $plan = $user->getPlanDeCroissance();
        if (null === $plan) {
            $plan = (new PlanDeCroissance())->setUser($user);
            $user->setPlanDeCroissance($plan);
            $this->em->persist($plan);
        }

        return (new GrowthGoal())->setPlanDeCroissance($plan)->setAxis($axis)->setType($type)->setProgress(0)
            ->setSourceType(self::SOURCE)->setSourceId($lesson->getId());
    }

    private function planDeVie(User $user): PlanDeVie
    {
        if (null === $user->getPlanDeVie()) {
            $plan = new PlanDeVie();
            $user->setPlanDeVie($plan);
            $this->em->persist($plan);
        }

        return $user->getPlanDeVie();
    }

    private function planDHygiene(User $user): PlanDHygiene
    {
        if (null === $user->getPlanDHygiene()) {
            $plan = (new PlanDHygiene())->setUser($user);
            $user->setPlanDHygiene($plan);
            $this->em->persist($plan);
        }

        return $user->getPlanDHygiene();
    }

    private function planDeTransmission(User $user): PlanDeTransmission
    {
        if (null === $user->getPlanDeTransmission()) {
            $plan = (new PlanDeTransmission())->setUser($user);
            $user->setPlanDeTransmission($plan);
            $this->em->persist($plan);
        }

        return $user->getPlanDeTransmission();
    }

    private function ownedLegacyGoal(User $user, int $id): LegacyGoal
    {
        $goal = $this->find($user, 'LegacyGoal', $id);
        if (!$goal instanceof LegacyGoal) {
            throw new PracticeRefused('Choisissez un legs de votre Plan de Transmission.');
        }

        return $goal;
    }
}
