<?php

namespace App\Academie\Service;

use App\Academie\Exception\PracticeRefused;
use App\Compte\Entity\User;
use App\PlanDeVie\Entity\InternalBalance;
use App\PlanDeVie\Entity\LifeGoal;
use App\PlanDeVie\Entity\Value;
use App\PlanDeVie\Service\LifePlanWizardSaver;
use Symfony\Component\HttpFoundation\InputBag;

/**
 * Parcours de plan (parcours guidés, 26/11/2026) : une séance écrit dans une **rubrique entière** du Plan de Vie
 * (raison d'être, valeurs, convictions, passions, dons et talents, vision, objectifs), et non un seul élément.
 *
 * L'enregistrement passe par l'assistant des plans (LifePlanWizardSaver) : mêmes champs, mêmes règles. Comme
 * l'assistant supprime ce qui n'est plus envoyé, la séance renvoie toujours les éléments déjà présents tels quels
 * (identifiant + texte) : elle **ajoute**, elle ne retire ni ne modifie rien. Corriger ou retirer se fait dans le plan.
 * Raison d'être et Vision sont un seul texte : la séance le montre prérempli, la personne le modifie elle-même.
 */
final class PlanRubrics
{
    /** action => [rubrique de l'assistant, étape, genre, champ de l'assistant, libellé] */
    public const RUBRICS = [
        'write_purpose' => ['step' => 1, 'kind' => 'text', 'field' => 'purpose_statement', 'label' => 'Votre raison d\'être (But de Dieu)', 'example' => 'Par exemple : aider les familles de mon quartier à grandir dans la foi et dans l\'unité.'],
        'list_convictions' => ['step' => 2, 'kind' => 'list', 'field' => 'convictions_deep', 'label' => 'Vos convictions profondes', 'example' => 'Par exemple : Dieu pourvoit à ce dont j\'ai besoin.'],
        'list_values' => ['step' => 2, 'kind' => 'list', 'field' => 'values_real', 'label' => 'Vos valeurs', 'example' => 'Par exemple : la fidélité'],
        'list_passions' => ['step' => 3, 'kind' => 'list', 'field' => 'passions', 'label' => 'Vos passions', 'example' => 'Par exemple : enseigner aux enfants'],
        'list_talents' => ['step' => 4, 'kind' => 'list', 'field' => 'talents', 'label' => 'Vos dons et talents', 'example' => 'Par exemple : l\'encouragement'],
        'write_vision' => ['step' => 6, 'kind' => 'text', 'field' => 'vision_content', 'label' => 'Votre vision', 'example' => 'Par exemple : dans cinq ans, notre foyer accueille chaque semaine des jeunes pour lire la Bible et partager un repas.'],
        'list_life_goals' => ['step' => 7, 'kind' => 'goals', 'field' => 'life_goals', 'label' => 'Vos objectifs de vie', 'example' => 'Par exemple : lire la Bible en entier cette année'],
    ];

    public const GOAL_CATEGORIES = ['BE' => 'Être', 'DO' => 'Faire', 'HAVE' => 'Avoir', 'GIVE' => 'Donner'];

    /** Nouveaux éléments ajoutés en une séance, au plus. */
    public const MAX_NEW = 10;

    public function __construct(private readonly LifePlanWizardSaver $saver)
    {
    }

    public static function isRubric(?string $action): bool
    {
        return isset(self::RUBRICS[(string) $action]);
    }

    /**
     * Ce que la séance affiche : le texte actuel (genre « text ») ou les éléments déjà présents (genres « list » et
     * « goals », par dimension pour les objectifs).
     *
     * @return array{kind: string, label: string, text?: string, horizon?: string, items?: list<array{id: int, text: string}>, goals?: array<string, array{label: string, items: list<array{id: int, text: string}>}>}
     */
    public function state(User $user, string $action): array
    {
        $def = self::RUBRICS[$action] ?? throw new PracticeRefused('Cet exercice n\'est plus disponible.');
        $plan = $user->getPlanDeVie();
        $state = ['kind' => $def['kind'], 'label' => $def['label'], 'example' => $def['example']];

        return match ($action) {
            'write_purpose' => $state + ['text' => (string) $plan?->getPurpose()?->getStatement()],
            'write_vision' => $state + ['text' => (string) $plan?->getVision()?->getContent(), 'horizon' => (string) ($plan?->getVision()?->getHorizon() ?: '5 ans')],
            'list_life_goals' => $state + ['goals' => $this->goalsByCategory($plan?->getLifeGoals()->toArray() ?? [])],
            default => $state + ['items' => $this->existing($user, $action)],
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
            $this->saver->save($user, $bag, [$def['step']]);

            return ['summary' => $text];
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

    /** @return list<array{id: int, text: string}> éléments déjà présents dans la rubrique */
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

    /**
     * @param LifeGoal[] $goals
     *
     * @return array<string, array{label: string, items: list<array{id: int, text: string}>}>
     */
    private function goalsByCategory(array $goals): array
    {
        $out = [];
        foreach (self::GOAL_CATEGORIES as $cat => $label) {
            $out[$cat] = ['label' => $label, 'items' => $this->items(array_filter($goals, fn (LifeGoal $g) => $g->getCategory() === $cat), fn (LifeGoal $g) => (string) $g->getTitle())];
        }

        return $out;
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
