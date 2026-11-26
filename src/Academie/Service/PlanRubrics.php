<?php

namespace App\Academie\Service;

use App\Academie\Exception\PracticeRefused;
use App\Compte\Entity\User;
use App\PlanDeCroissance\Entity\GrowthGoal;
use App\PlanDeCroissance\Enum\GrowthAxis;
use App\PlanDeVie\Entity\InternalBalance;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\Value;
use App\PlanDeVie\Service\LifePlanWizardSaver;
use App\PlanDIntendance\Entity\StewardshipItem;
use App\PlanDIntendance\Service\Stewardship;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Parcours de plan (parcours guidés, 26/11/2026) : une séance écrit dans une **rubrique entière** d'un des 6 plans, et
 * non un seul élément.
 *
 * L'enregistrement passe par l'assistant des plans (LifePlanWizardSaver) : mêmes champs, mêmes règles. Comme
 * l'assistant supprime ce qui n'est plus envoyé (éléments, et liens vers les objectifs du Plan de Vie), la séance
 * renvoie toujours les éléments déjà présents tels quels (identifiant, texte, liens, échéances, et les rubriques sœurs
 * enregistrées par la même étape) : elle **ajoute**, elle ne retire ni ne modifie rien. Corriger ou retirer se fait
 * dans le plan. Un texte unique (raison d'être, vision, phrase d'éternité) est montré prérempli : la personne le
 * modifie elle-même. Le Plan d'Intendance n'a pas d'étape d'assistant : ses éléments sont créés directement.
 *
 * Genres : « text » (un texte), « list » (une liste simple), « indexed » (blocs de l'assistant avec identifiant et
 * objectifs soutenus), « goals » (listes par groupe : dimensions des objectifs, domaines de l'intendance).
 */
final class PlanRubrics
{
    public const RUBRICS = [
        // Plan de Vie
        'write_purpose' => ['plan' => 'VIE', 'step' => 1, 'kind' => 'text', 'field' => 'purpose_statement', 'label' => 'Votre raison d\'être (But de Dieu)', 'example' => 'Par exemple : aider les familles de mon quartier à grandir dans la foi et dans l\'unité.'],
        'list_convictions' => ['plan' => 'VIE', 'step' => 2, 'kind' => 'list', 'field' => 'convictions_deep', 'label' => 'Vos convictions profondes', 'example' => 'Par exemple : Dieu pourvoit à ce dont j\'ai besoin.'],
        'list_values' => ['plan' => 'VIE', 'step' => 2, 'kind' => 'list', 'field' => 'values_real', 'label' => 'Vos valeurs', 'example' => 'Par exemple : la fidélité'],
        'list_passions' => ['plan' => 'VIE', 'step' => 3, 'kind' => 'list', 'field' => 'passions', 'label' => 'Vos passions', 'example' => 'Par exemple : enseigner aux enfants'],
        'list_talents' => ['plan' => 'VIE', 'step' => 4, 'kind' => 'list', 'field' => 'talents', 'label' => 'Vos dons et talents', 'example' => 'Par exemple : l\'encouragement'],
        'write_vision' => ['plan' => 'VIE', 'step' => 6, 'kind' => 'text', 'field' => 'vision_content', 'label' => 'Votre vision', 'example' => 'Par exemple : dans cinq ans, notre foyer accueille chaque semaine des jeunes pour lire la Bible et partager un repas.'],
        'list_life_goals' => ['plan' => 'VIE', 'step' => 7, 'kind' => 'goals', 'field' => 'life_goals', 'label' => 'Vos objectifs de vie', 'example' => 'Par exemple : lire la Bible en entier cette année'],
        // Plan de Croissance
        'list_character_goals' => ['plan' => 'CROISSANCE', 'step' => 9, 'kind' => 'indexed', 'group' => 'character', 'label' => 'Vos chantiers de caractère', 'example' => 'Par exemple : devenir patient avec mes enfants'],
        'list_relation_goals' => ['plan' => 'CROISSANCE', 'step' => 10, 'kind' => 'indexed', 'group' => 'relation', 'label' => 'Vos objectifs de relation et de réseau', 'example' => 'Par exemple : trouver un mentor dans ma vocation'],
        // Plan de Développement personnel (même étape que les connaissances : les deux listes sont toujours renvoyées)
        'list_skills' => ['plan' => 'DEVELOPPEMENT', 'step' => 8, 'kind' => 'indexed', 'group' => 'competence', 'siblings' => ['connaissance'], 'label' => 'Les compétences à développer', 'example' => 'Par exemple : parler en public'],
        'list_knowledge' => ['plan' => 'DEVELOPPEMENT', 'step' => 8, 'kind' => 'indexed', 'group' => 'connaissance', 'siblings' => ['competence'], 'label' => 'Les connaissances à acquérir', 'example' => 'Par exemple : les bases de la gestion d\'un budget'],
        // Plan d'Hygiène de vie
        'list_hygiene_domains' => ['plan' => 'HYGIENE', 'step' => 12, 'kind' => 'indexed', 'group' => 'hygiene', 'label' => 'Les domaines de votre hygiène à soigner', 'example' => 'Par exemple : le sommeil'],
        // Plan d'Intendance (pas d'étape d'assistant)
        'list_stewardship' => ['plan' => 'INTENDANCE', 'step' => null, 'kind' => 'goals', 'field' => 'stewardship', 'label' => 'Ce qui vous est confié', 'example' => 'Par exemple : mon épargne de sécurité'],
        // Plan de Transmission
        'write_eternity' => ['plan' => 'TRANSMISSION', 'step' => 11, 'kind' => 'text', 'field' => 'eternity_phrase', 'label' => 'Votre phrase d\'éternité', 'example' => 'Par exemple : j\'ai aimé Dieu et j\'ai aidé des jeunes à trouver leur place dans son œuvre.'],
        'list_legacy_goals' => ['plan' => 'TRANSMISSION', 'step' => 11, 'kind' => 'indexed', 'group' => 'legacy', 'label' => 'Ce que vous voulez transmettre', 'example' => 'Par exemple : transmettre à mes enfants l\'amour de la Parole'],
    ];

    /** Blocs de l'assistant : préfixe des champs, champ du titre. */
    private const GROUPS = [
        'character' => ['prefix' => 'growth_character', 'title' => 'growth_character_title'],
        'relation' => ['prefix' => 'growth_relation', 'title' => 'growth_relation_title'],
        'competence' => ['prefix' => 'growth_competence', 'title' => 'growth_competence_title'],
        'connaissance' => ['prefix' => 'growth_connaissance', 'title' => 'growth_connaissance_title'],
        'hygiene' => ['prefix' => 'hygiene', 'title' => 'hygiene_name'],
        'legacy' => ['prefix' => 'legacy', 'title' => 'legacy_title'],
    ];

    public const GOAL_CATEGORIES = ['BE' => 'Être', 'DO' => 'Faire', 'HAVE' => 'Avoir', 'GIVE' => 'Donner'];

    /** Nouveaux éléments ajoutés en une séance, au plus. */
    public const MAX_NEW = 10;

    public function __construct(
        private readonly LifePlanWizardSaver $saver,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public static function isRubric(?string $action): bool
    {
        return isset(self::RUBRICS[(string) $action]);
    }

    /** @return array<string, string> groupes d'une rubrique « goals » : clé => libellé */
    public static function groups(string $action): array
    {
        return 'list_stewardship' === $action
            ? array_map(fn (array $d) => $d['label'], Stewardship::DOMAINS)
            : self::GOAL_CATEGORIES;
    }

    /**
     * Ce que la séance affiche : le texte actuel ou les éléments déjà présents.
     *
     * @return array{kind: string, label: string, example: string, text?: string, horizon?: string, items?: list<array{id: int, text: string}>, goals?: array<string, array{label: string, items: list<array{id: int, text: string}>}>}
     */
    public function state(User $user, string $action): array
    {
        $def = self::RUBRICS[$action] ?? throw new PracticeRefused('Cet exercice n\'est plus disponible.');
        $plan = $user->getPlanDeVie();
        // « indexed » s'affiche comme une liste
        $state = ['kind' => 'indexed' === $def['kind'] ? 'list' : $def['kind'], 'label' => $def['label'], 'example' => $def['example']];

        return match ($action) {
            'write_purpose' => $state + ['text' => (string) $plan?->getPurpose()?->getStatement()],
            'write_vision' => $state + ['text' => (string) $plan?->getVision()?->getContent(), 'horizon' => (string) ($plan?->getVision()?->getHorizon() ?: '5 ans')],
            'write_eternity' => $state + ['text' => (string) $user->getPlanDeTransmission()?->getEternityPhrase()],
            'list_life_goals' => $state + ['goals' => $this->byGroup(self::GOAL_CATEGORIES, $plan?->getLifeGoals()->toArray() ?? [], fn (LifeGoal $g) => $g->getCategory(), fn (LifeGoal $g) => (string) $g->getTitle())],
            'list_stewardship' => $state + ['goals' => $this->byGroup(self::groups($action), $this->stewardship($user), fn (StewardshipItem $i) => $i->getOrigin(), fn (StewardshipItem $i) => $i->getLabel())],
            default => $state + ['items' => 'indexed' === $def['kind']
                ? $this->items($this->indexed($user, $def['group']), fn (object $e) => $this->indexedTitle($e))
                : $this->existing($user, $action)],
        };
    }

    /**
     * Enregistre la séance dans le plan.
     *
     * @param array{text?: string, horizon?: string, items?: array<mixed>, goals?: array<string, mixed>} $input
     *
     * @return array{summary: string} ce qui vient d'être posé, pour la célébration
     *
     * @throws PracticeRefused message à montrer au membre
     */
    public function save(User $user, string $action, array $input): array
    {
        $def = self::RUBRICS[$action] ?? throw new PracticeRefused('Cet exercice n\'est plus disponible.');
        $bag = new InputBag();

        if ('text' === $def['kind']) {
            $text = trim(mb_substr((string) ($input['text'] ?? ''), 0, 'write_vision' === $action ? 5000 : 2000));
            if ('' === $text) {
                throw new PracticeRefused('Écrivez d\'abord votre réponse.');
            }
            $bag->set($def['field'], $text);
            if ('write_vision' === $action) {
                $horizon = trim(mb_substr((string) ($input['horizon'] ?? ''), 0, 50));
                $bag->set('vision_horizon', '' !== $horizon ? $horizon : ((string) $user->getPlanDeVie()?->getVision()?->getHorizon() ?: '5 ans'));
            }
            if ('write_eternity' === $action) {
                // La même étape enregistre le déplacement de trésor : il est renvoyé tel quel
                $bag->set('treasure_shift', (string) $user->getPlanDeTransmission()?->getTreasureShift());
            }
            $this->saver->save($user, $bag, [$def['step']]);

            return ['summary' => $text];
        }

        if ('list_stewardship' === $action) {
            return $this->saveStewardship($user, $input['goals'] ?? []);
        }

        if ('goals' === $def['kind']) {
            $added = [];
            $goals = $user->getPlanDeVie()?->getLifeGoals()->toArray() ?? [];
            foreach (array_keys(self::GOAL_CATEGORIES) as $cat) {
                $existing = array_values(array_filter($goals, fn (LifeGoal $g) => $g->getCategory() === $cat && $g->getId()));
                $new = $this->newItems($input['goals'][$cat] ?? [], array_map(fn (LifeGoal $g) => (string) $g->getTitle(), $existing));
                $field = 'life_goals_'.strtolower($cat);
                $bag->set($field, [...array_map(fn (LifeGoal $g) => (string) $g->getTitle(), $existing), ...$new]);
                $bag->set($field.'_ids', [...array_map(fn (LifeGoal $g) => (string) $g->getId(), $existing), ...array_fill(0, \count($new), '')]);
                // Les échéances déjà posées sont renvoyées telles quelles (l'assistant les remplace sinon par « aucune »)
                $bag->set($field.'_deadlines', [...array_map(fn (LifeGoal $g) => (string) $g->getDeadline()?->format('Y-m-d'), $existing), ...array_fill(0, \count($new), '')]);
                $added = [...$added, ...$new];
            }
            if ([] === $added) {
                throw new PracticeRefused('Écrivez au moins un objectif.');
            }
            $this->saver->save($user, $bag, [$def['step']]);

            return ['summary' => implode(', ', $added)];
        }

        if ('indexed' === $def['kind']) {
            $existing = $this->indexed($user, $def['group']);
            $new = $this->newItems($input['items'] ?? [], array_map(fn (object $e) => $this->indexedTitle($e), $existing));
            if ([] === $new) {
                throw new PracticeRefused([] === $existing ? 'Écrivez au moins une réponse.' : 'Ajoutez au moins un élément nouveau, ou terminez la séance sans modifier votre plan.');
            }
            $this->putIndexed($bag, $def['group'], $existing, $new);
            foreach ($def['siblings'] ?? [] as $sibling) {
                $this->putIndexed($bag, $sibling, $this->indexed($user, $sibling), []);
            }
            $this->saver->save($user, $bag, [$def['step']]);

            return ['summary' => implode(', ', $new)];
        }

        $existing = $this->existing($user, $action);
        $new = $this->newItems($input['items'] ?? [], array_column($existing, 'text'));
        if ([] === $new) {
            throw new PracticeRefused([] === $existing ? 'Écrivez au moins une réponse.' : 'Ajoutez au moins un élément nouveau, ou terminez la séance sans modifier votre plan.');
        }
        $bag->set($def['field'], [...array_column($existing, 'text'), ...$new]);
        $bag->set($def['field'].'_ids', [...array_map('strval', array_column($existing, 'id')), ...array_fill(0, \count($new), '')]);
        $this->saver->save($user, $bag, [$def['step']]);

        return ['summary' => implode(', ', $new)];
    }

    /**
     * Un bloc de l'assistant : les éléments existants (identifiant, titre, objectifs soutenus), puis les nouveaux.
     *
     * @param object[]     $existing
     * @param list<string> $new
     */
    private function putIndexed(InputBag $bag, string $group, array $existing, array $new): void
    {
        $g = self::GROUPS[$group];
        $titles = $ids = $links = [];
        foreach (array_values($existing) as $i => $e) {
            $titles[$i] = $this->indexedTitle($e);
            $ids[$i] = (string) $e->getId();
            $links[$i] = array_map(fn (LifeGoal $lg) => (string) $lg->getId(), $e->getSupportedLifeGoals()->toArray());
        }
        foreach ($new as $title) {
            $titles[] = $title;
            $ids[] = '';
            $links[] = [];
        }
        $bag->set($g['title'], $titles);
        $bag->set($g['prefix'].'_id', $ids);
        $bag->set($g['prefix'].'_goals', $links);
    }

    /** @return object[] éléments d'un bloc, filtrés exactement comme l'assistant les rapproche (LifePlanWizardSaver) */
    private function indexed(User $user, string $group): array
    {
        $growth = $user->getPlanDeCroissance()?->getGrowthGoals()->toArray() ?? [];
        $own = fn (GrowthGoal $g) => !$g->getSourceType() && null !== $g->getId();

        return array_values(match ($group) {
            'character' => array_filter($growth, fn (GrowthGoal $g) => $own($g) && GrowthAxis::CHARACTER === $g->getAxis()),
            'relation' => array_filter($growth, fn (GrowthGoal $g) => $own($g) && GrowthAxis::RELATION === $g->getAxis()),
            'competence' => array_filter($growth, fn (GrowthGoal $g) => $own($g) && GrowthAxis::COMPETENCE === $g->getAxis() && 'COMPETENCE' === ($g->getType() ?: 'COMPETENCE')),
            'connaissance' => array_filter($growth, fn (GrowthGoal $g) => $own($g) && GrowthAxis::COMPETENCE === $g->getAxis() && 'CONNAISSANCE' === $g->getType()),
            'hygiene' => array_filter($user->getPlanDHygiene()?->getHygieneDomains()->toArray() ?? [], fn ($d) => null !== $d->getId()),
            'legacy' => array_filter($user->getPlanDeTransmission()?->getLegacyGoals()->toArray() ?? [], fn ($l) => null !== $l->getId()),
        });
    }

    private function indexedTitle(object $e): string
    {
        return (string) (method_exists($e, 'getTitle') ? $e->getTitle() : $e->getName());
    }

    /** @return list<StewardshipItem> */
    private function stewardship(User $user): array
    {
        return $this->em->getRepository(StewardshipItem::class)->findBy(['user' => $user], ['position' => 'ASC']);
    }

    /**
     * Intendance : de nouveaux éléments par domaine, créés directement (pas d'étape d'assistant).
     *
     * @return array{summary: string}
     */
    private function saveStewardship(User $user, mixed $raw): array
    {
        $existing = $this->stewardship($user);
        $position = \count($existing);
        $added = [];
        foreach (array_keys(Stewardship::DOMAINS) as $origin) {
            $labels = array_map(fn (StewardshipItem $i) => $i->getLabel(), array_filter($existing, fn (StewardshipItem $i) => $i->getOrigin() === $origin));
            foreach ($this->newItems(\is_array($raw) ? ($raw[$origin] ?? []) : [], array_values($labels)) as $label) {
                $this->em->persist((new StewardshipItem())->setUser($user)->setOrigin($origin)->setLabel($label)->setPosition($position++));
                $added[] = $label;
            }
        }
        if ([] === $added) {
            throw new PracticeRefused('Écrivez au moins un élément.');
        }
        $this->em->flush();

        return ['summary' => implode(', ', $added)];
    }

    /**
     * @param array<string, string> $groups
     *
     * @return array<string, array{label: string, items: list<array{id: int, text: string}>}>
     */
    private function byGroup(array $groups, array $entities, callable $group, callable $text): array
    {
        $out = [];
        foreach ($groups as $key => $label) {
            $out[$key] = ['label' => $label, 'items' => $this->items(array_filter($entities, fn ($e) => $group($e) === $key), $text)];
        }

        return $out;
    }

    /** @return list<array{id: int, text: string}> éléments déjà présents dans une liste simple du Plan de Vie */
    private function existing(User $user, string $action): array
    {
        $plan = $user->getPlanDeVie();

        return match ($action) {
            'list_values' => $this->items(array_filter($plan?->getValues()->toArray() ?? [], fn (Value $v) => ($v->getCategory() ?: 'real') === 'real'), fn (Value $v) => (string) $v->getName()),
            'list_passions' => $this->items($plan?->getPassions()->toArray() ?? [], fn ($p) => (string) $p->getName()),
            'list_convictions' => $this->items(array_filter($user->getInternalBalances()->toArray(), fn (InternalBalance $ib) => 'CONVICTION' === $ib->getType() && ($ib->getSubtype() ?: 'deep') === 'deep'), fn (InternalBalance $ib) => (string) $ib->getText()),
            'list_talents' => $this->items(array_filter($user->getInternalBalances()->toArray(), fn (InternalBalance $ib) => 'TALENT' === $ib->getType()), fn (InternalBalance $ib) => (string) $ib->getText()),
            default => [],
        };
    }

    /** @return list<array{id: int, text: string}> */
    private function items(array $entities, callable $text): array
    {
        $out = [];
        foreach ($entities as $e) {
            if ($e->getId()) {
                $out[] = ['id' => (int) $e->getId(), 'text' => $text($e)];
            }
        }

        return $out;
    }

    /**
     * Lignes nouvelles : nettoyées, sans vide, sans doublon (entre elles ni avec l'existant, sans tenir compte de la casse).
     *
     * @param list<string> $existing
     *
     * @return list<string>
     */
    private function newItems(mixed $raw, array $existing): array
    {
        $seen = array_map(fn (string $s) => mb_strtolower(trim($s)), $existing);
        $out = [];
        foreach (\is_array($raw) ? $raw : [$raw] as $line) {
            $line = trim(mb_substr(\is_string($line) ? $line : '', 0, 255));
            $key = mb_strtolower($line);
            if ('' === $line || \in_array($key, $seen, true)) {
                continue;
            }
            $seen[] = $key;
            $out[] = $line;
            if (\count($out) >= self::MAX_NEW) {
                break;
            }
        }

        return $out;
    }
}
