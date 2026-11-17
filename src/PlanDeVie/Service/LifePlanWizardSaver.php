<?php

namespace App\PlanDeVie\Service;

use App\Compte\Entity\User;
use App\Personnalite\Entity\ProfileTest;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Entity\PlanDeCroissance;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDeTransmission\Entity\LegacyGoal;
use App\PlanDeTransmission\Entity\PlanDeTransmission;
use App\PlanDeVie\Entity\InternalBalance;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\Passion;
use App\PlanDeVie\Entity\PlanDeVie;
use App\PlanDeVie\Entity\Purpose;
use App\PlanDeVie\Entity\Value;
use App\PlanDeVie\Entity\Vision;
use App\PlanDHygiene\Entity\HygieneDomain;
use App\PlanDHygiene\Entity\PlanDHygiene;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Enregistre les étapes de l'assistant des plans.
 *
 * Mise à jour incrémentale : chaque ligne envoyée est rapprochée de l'élément existant
 * (par son identifiant, à défaut par son libellé), qui est modifié et non recréé.
 * Les liens (rituels, objectifs de croissance rattachés à une valeur ou une croyance,
 * objectifs soutenus, progression, jalons, historique) sont donc conservés.
 * Seuls les éléments retirés par l'utilisateur sont supprimés.
 *
 * Seuls les éléments de l'utilisateur peuvent être modifiés ou liés : un identifiant
 * qui ne lui appartient pas est ignoré.
 */
final class LifePlanWizardSaver
{
    /** Étapes de l'assistant pour chaque plan (null = Plan de Vie). Voir docs/charte-projet.md §1bis. */
    public const STEPS_BY_PLAN = [
        '' => [1, 2, 3, 4, 5, 6, 7, 13],
        'CROISSANCE' => [2, 9, 10],
        'DEVELOPPEMENT' => [8],
        'TRANSMISSION' => [11],
        'HYGIENE' => [12],
    ];

    public const SUBTYPES = ['build', 'consolidate', 'leverage', 'absence'];
    public const LIFE_GOAL_CATEGORIES = ['BE', 'DO', 'HAVE', 'GIVE'];

    /** @var array<string, array<int, object|null>> lignes envoyées → éléments enregistrés, pour renvoyer les identifiants */
    private array $saved = [];

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * @param int[] $steps étapes à enregistrer
     *
     * @return array<string, array<int, int|null>> identifiants des lignes, par champ (pour l'enregistrement par étape)
     */
    public function save(User $user, InputBag $input, array $steps): array
    {
        $this->saved = [];
        $planDeVie = $this->planDeVie($user);
        $has = fn (string ...$keys) => array_filter($keys, fn ($k) => $input->has($k)) !== [];

        if (\in_array(1, $steps, true) && $input->has('purpose_statement')) {
            $purpose = $planDeVie->getPurpose() ?? (new Purpose())->setPlanDeVie($planDeVie);
            $purpose->setStatement(trim($input->getString('purpose_statement')));
            $this->em->persist($purpose);
        }

        if (\in_array(2, $steps, true)) {
            if ($has('values_real', 'values_declared', 'values_counter')) {
                $this->saveValues($planDeVie, $input);
            }
            if ($has('convictions_deep', 'convictions_medium', 'convictions_light', 'limiting_beliefs')) {
                $this->saveInternalBalances($user, $input, [
                    'convictions_deep' => ['CONVICTION', 'deep'],
                    'convictions_medium' => ['CONVICTION', 'medium'],
                    'convictions_light' => ['CONVICTION', 'light'],
                    'limiting_beliefs' => ['LIMITING_BELIEF', null],
                ]);
            }
        }

        if (\in_array(3, $steps, true) && $input->has('passions')) {
            $rows = $this->rows($input, 'passions');
            $this->reconcile('passions', $planDeVie->getPassions()->toArray(), $rows, fn ($p) => $p->getName(),
                fn () => (new Passion())->setPlanDeVie($planDeVie),
                fn (Passion $p, array $r) => $p->setName($r['text']));
        }

        if (\in_array(4, $steps, true) && $input->has('talents')) {
            $this->saveInternalBalances($user, $input, ['talents' => ['TALENT', null]]);
        }

        if (\in_array(5, $steps, true)) {
            $this->saveManualTests($user, $input);
        }

        if (\in_array(6, $steps, true) && $input->has('vision_content')) {
            $vision = $planDeVie->getVision() ?? (new Vision())->setPlanDeVie($planDeVie)->setBeneficiaries('')->setIndicators('');
            $vision->setContent(trim($input->getString('vision_content')));
            $vision->setHorizon(trim($input->getString('vision_horizon')) ?: '5 ans');
            $this->em->persist($vision);
        }

        if (\in_array(7, $steps, true) && $has('life_goals_be', 'life_goals_do', 'life_goals_have', 'life_goals_give')) {
            $this->saveLifeGoals($planDeVie, $input);
        }

        $lifeGoals = $this->ownedLifeGoals($planDeVie);

        if (\in_array(8, $steps, true) && $has('growth_competence_title', 'growth_connaissance_title')) {
            $plan = $this->planDeCroissance($user);
            foreach (['competence' => 'COMPETENCE', 'connaissance' => 'CONNAISSANCE'] as $prefix => $type) {
                $existing = array_filter($plan->getGrowthGoals()->toArray(), fn (GrowthGoal $g) => GrowthAxis::COMPETENCE === $g->getAxis() && ($g->getType() ?: 'COMPETENCE') === $type && !$g->getSourceType());
                $this->saveGrowthGoals($plan, $input, $prefix, GrowthAxis::COMPETENCE, $existing, $lifeGoals, fn (GrowthGoal $g) => $g->setType($type));
            }
        }
        if (\in_array(9, $steps, true) && $input->has('growth_character_title')) {
            $plan = $this->planDeCroissance($user);
            // Les objectifs nés d'une croyance ou d'une valeur (axes de la page Croissance) sont gérés ailleurs
            $existing = array_filter($plan->getGrowthGoals()->toArray(), fn (GrowthGoal $g) => GrowthAxis::CHARACTER === $g->getAxis() && !$g->getSourceType());
            $this->saveGrowthGoals($plan, $input, 'character', GrowthAxis::CHARACTER, $existing, $lifeGoals);
        }
        if (\in_array(10, $steps, true) && $input->has('growth_relation_title')) {
            $plan = $this->planDeCroissance($user);
            $existing = array_filter($plan->getGrowthGoals()->toArray(), fn (GrowthGoal $g) => GrowthAxis::RELATION === $g->getAxis() && !$g->getSourceType());
            $types = $input->all('growth_relation_type');
            $this->saveGrowthGoals($plan, $input, 'relation', GrowthAxis::RELATION, $existing, $lifeGoals,
                fn (GrowthGoal $g, int|string $i) => $g->setType($types[$i] ?? $g->getType() ?? 'orientation'));
        }

        if (\in_array(11, $steps, true) && $input->has('eternity_phrase')) {
            $plan = $user->getPlanDeTransmission() ?? $this->persistNew((new PlanDeTransmission())->setUser($user));
            $plan->setEternityPhrase(mb_substr($input->getString('eternity_phrase'), 0, 2000))
                ->setTreasureShift(mb_substr($input->getString('treasure_shift'), 0, 2000));
        }

        if (\in_array(11, $steps, true) && $input->has('legacy_title')) {
            $plan = $user->getPlanDeTransmission() ?? $this->persistNew((new PlanDeTransmission())->setUser($user));
            $this->saveIndexedGoals('legacy', $input, $plan->getLegacyGoals()->toArray(), $lifeGoals,
                fn () => $this->attach(new LegacyGoal(), fn ($lg) => $plan->addLegacyGoal($lg)),
                fn (LegacyGoal $lg, string $title) => $lg->setTitle($title), fn (LegacyGoal $lg) => $lg->getTitle());
        }

        if (\in_array(12, $steps, true) && $has('hygiene_name', 'hygiene_domain_name')) {
            $plan = $user->getPlanDHygiene() ?? $this->persistNew((new PlanDHygiene())->setUser($user));
            $prefix = $input->has('hygiene_name') ? 'hygiene_name' : 'hygiene_domain_name';
            $this->saveIndexedGoals('hygiene', $input, $plan->getHygieneDomains()->toArray(), $lifeGoals,
                fn () => $this->attach(new HygieneDomain(), fn ($hd) => $plan->addHygieneDomain($hd)),
                fn (HygieneDomain $hd, string $name) => $hd->setName($name), fn (HygieneDomain $hd) => $hd->getName(), $prefix);
        }

        $this->em->flush();

        return array_map(fn (array $rows) => array_map(fn (?object $e) => $e?->getId(), $rows), $this->saved);
    }

    private function saveValues(PlanDeVie $plan, InputBag $input): void
    {
        $all = $plan->getValues()->toArray();
        foreach (['values_real' => 'real', 'values_declared' => 'declared', 'values_counter' => 'counter'] as $field => $category) {
            if (!$input->has($field)) {
                continue;
            }
            $existing = array_filter($all, fn (Value $v) => ($v->getCategory() ?: 'real') === $category);
            $this->reconcile($field, $existing, $this->rows($input, $field), fn (Value $v) => $v->getName(),
                fn () => (new Value())->setPlanDeVie($plan)->setBehavior('')->setCategory($category),
                fn (Value $v, array $r) => $v->setName($r['text']));
        }
    }

    /** @param array<string, array{0: string, 1: ?string}> $fields champ du formulaire => [type, sous-type] */
    private function saveInternalBalances(User $user, InputBag $input, array $fields): void
    {
        $all = $user->getInternalBalances()->toArray();
        foreach ($fields as $field => [$type, $subtype]) {
            if (!$input->has($field)) {
                continue;
            }
            $existing = array_filter($all, fn (InternalBalance $ib) => $ib->getType() === $type
                && ('CONVICTION' !== $type || ($ib->getSubtype() ?: 'deep') === $subtype));
            $this->reconcile($field, $existing, $this->rows($input, $field), fn (InternalBalance $ib) => $ib->getText(),
                function () use ($user, $type, $subtype) {
                    $ib = (new InternalBalance())->setType($type)->setSubtype($subtype);
                    $user->addInternalBalance($ib);

                    return $ib;
                },
                fn (InternalBalance $ib, array $r) => $ib->setText($r['text']));
        }
    }

    private function saveLifeGoals(PlanDeVie $plan, InputBag $input): void
    {
        $all = $plan->getLifeGoals()->toArray();
        foreach (self::LIFE_GOAL_CATEGORIES as $category) {
            $field = 'life_goals_'.strtolower($category);
            if (!$input->has($field)) {
                continue;
            }
            $deadlines = array_values($input->all($field.'_deadlines'));
            $rows = $this->rows($input, $field);
            foreach ($rows as $i => &$row) {
                $row['deadline'] = $deadlines[$i] ?? null;
            }
            unset($row);
            $existing = array_filter($all, fn (LifeGoal $g) => $g->getCategory() === $category);
            $this->reconcile($field, $existing, $rows, fn (LifeGoal $g) => $g->getTitle(),
                fn () => (new LifeGoal())->setPlanDeVie($plan)->setCategory($category)->setProgress(0),
                function (LifeGoal $g, array $r) {
                    $g->setTitle($r['text']);
                    $date = $r['deadline'] ? \DateTime::createFromFormat('!Y-m-d', trim($r['deadline'])) : null;
                    $g->setDeadline($date ?: null);
                });
        }
    }

    /** Résultats saisis à la main (MBTI, DISC…) : une nouvelle entrée seulement si la valeur change, l'historique est conservé. */
    private function saveManualTests(User $user, InputBag $input): void
    {
        foreach (['profile_mbti' => 'MBTI', 'profile_disc' => 'DISC', 'profile_enneagram' => 'ENNEAGRAM', 'profile_big5' => 'BIG5'] as $field => $type) {
            $value = trim($input->getString($field));
            if (!$input->has($field) || '' === $value) {
                continue;
            }
            $latest = null;
            foreach ($user->getProfileTests() as $pt) {
                if (strtoupper($pt->getType()) === $type && (null === $latest || $pt->getDate() > $latest->getDate())) {
                    $latest = $pt;
                }
            }
            $current = $latest?->getResult();
            if ($current && str_starts_with($current, '{')) {
                $current = json_decode($current, true)['type'] ?? $current;
            }
            if ($current !== $value) {
                $this->em->persist((new ProfileTest())->setUser($user)->setType($type)->setResult($value)->setDate(new \DateTime()));
            }
        }
    }

    /**
     * @param GrowthGoal[]  $existing
     * @param LifeGoal[]    $lifeGoals objectifs de l'utilisateur, par identifiant
     */
    private function saveGrowthGoals(PlanDeCroissance $plan, InputBag $input, string $prefix, GrowthAxis $axis, array $existing, array $lifeGoals, ?callable $extra = null): void
    {
        $strategies = $input->all("growth_{$prefix}_strategy");
        $this->saveIndexedGoals("growth_{$prefix}", $input, $existing, $lifeGoals,
            fn () => $this->attach((new GrowthGoal())->setAxis($axis)->setProgress(0), fn ($g) => $plan->addGrowthGoal($g)),
            function (GrowthGoal $g, string $title, int|string $i) use ($strategies, $extra) {
                $g->setTitle($title);
                if (\array_key_exists($i, $strategies)) {
                    $g->setStrategy($strategies[$i]);
                }
                if ($extra) {
                    $extra($g, $i);
                }
            },
            fn (GrowthGoal $g) => $g->getTitle(), "growth_{$prefix}_title");
    }

    /**
     * Blocs indexés (titre[i], statut[i], objectifs soutenus[i][], identifiant[i]) :
     * objectifs de croissance, projets de transmission, domaines d'hygiène.
     */
    private function saveIndexedGoals(string $key, InputBag $input, array $existing, array $lifeGoals, callable $factory, callable $setTitle, callable $getTitle, ?string $titleField = null): void
    {
        $titleField ??= $key.'_title';
        $statuses = $input->all($key.'_status');
        $links = $input->all($key.'_goals');
        $ids = $input->all($key.'_id');

        $rows = [];
        foreach ($input->all($titleField) as $i => $title) {
            $rows[$i] = ['text' => trim((string) $title), 'id' => $ids[$i] ?? null];
        }

        $this->reconcile($titleField, $existing, $rows, $getTitle, $factory,
            function (object $goal, array $r, int|string $i) use ($statuses, $links, $lifeGoals, $setTitle) {
                $setTitle($goal, $r['text'], $i);
                $status = $statuses[$i] ?? null;
                $goal->setSubtype(\in_array($status, self::SUBTYPES, true) ? $status : ($goal->getSubtype() ?: 'build'));
                $wanted = array_filter(array_map(fn ($id) => $lifeGoals[(int) $id] ?? null, (array) ($links[$i] ?? [])));
                foreach ($goal->getSupportedLifeGoals()->toArray() as $lg) {
                    if (!\in_array($lg, $wanted, true)) {
                        $goal->removeSupportedLifeGoal($lg);
                    }
                }
                foreach ($wanted as $lg) {
                    $goal->addSupportedLifeGoal($lg);
                }
            });
    }

    /**
     * Rapproche les lignes envoyées des éléments existants.
     *
     * @param object[]                           $existing éléments actuels (de l'utilisateur uniquement)
     * @param array<int|string, array{text: string, id: mixed}> $rows lignes du formulaire
     */
    private function reconcile(string $key, array $existing, array $rows, callable $label, callable $factory, callable $apply): void
    {
        $byId = [];
        foreach ($existing as $e) {
            $byId[$e->getId()] = $e;
        }
        $normalize = fn (?string $s) => mb_strtolower(trim((string) $s));
        $kept = [];
        $this->saved[$key] = [];

        // 1. Par identifiant
        $pending = [];
        foreach ($rows as $i => $row) {
            if ('' === $row['text']) {
                $this->saved[$key][$i] = null;
                continue;
            }
            $id = is_numeric($row['id'] ?? null) ? (int) $row['id'] : null;
            if (null !== $id && isset($byId[$id]) && !isset($kept[$id])) {
                $kept[$id] = true;
                $apply($byId[$id], $row, $i);
                $this->saved[$key][$i] = $byId[$id];
            } else {
                $pending[$i] = $row;
            }
        }
        // 2. Par libellé (formulaires sans identifiant), sinon création
        foreach ($pending as $i => $row) {
            $match = null;
            foreach ($existing as $e) {
                if (!isset($kept[$e->getId()]) && null !== $e->getId() && $normalize($label($e)) === $normalize($row['text'])) {
                    $match = $e;
                    break;
                }
            }
            if ($match) {
                $kept[$match->getId()] = true;
            } else {
                $match = $factory();
                $this->em->persist($match);
            }
            $apply($match, $row, $i);
            $this->saved[$key][$i] = $match;
        }
        // 3. Les éléments retirés par l'utilisateur sont supprimés
        foreach ($existing as $e) {
            if (null !== $e->getId() && !isset($kept[$e->getId()])) {
                $this->em->remove($e);
            }
        }
        ksort($this->saved[$key]);
    }

    /** Lignes d'une liste simple : texte[] et identifiants alignés dans <champ>_ids[]. */
    private function rows(InputBag $input, string $field): array
    {
        $ids = array_values($input->all($field.'_ids'));
        $rows = [];
        foreach (array_values($input->all($field)) as $i => $text) {
            $rows[$i] = ['text' => trim((string) $text), 'id' => $ids[$i] ?? null];
        }

        return $rows;
    }

    /** @return array<int, LifeGoal> objectifs de vie de l'utilisateur, par identifiant */
    private function ownedLifeGoals(PlanDeVie $plan): array
    {
        $goals = [];
        foreach ($plan->getLifeGoals() as $lg) {
            if ($lg->getId()) {
                $goals[$lg->getId()] = $lg;
            }
        }

        return $goals;
    }

    private function planDeVie(User $user): PlanDeVie
    {
        if (null === $user->getPlanDeVie()) {
            $user->setPlanDeVie($this->persistNew(new PlanDeVie()));
        }

        return $user->getPlanDeVie();
    }

    private function planDeCroissance(User $user): PlanDeCroissance
    {
        return $user->getPlanDeCroissance() ?? $this->persistNew((new PlanDeCroissance())->setUser($user));
    }

    private function persistNew(object $entity): object
    {
        $this->em->persist($entity);

        return $entity;
    }

    private function attach(object $entity, callable $attach): object
    {
        $attach($entity);

        return $entity;
    }
}
