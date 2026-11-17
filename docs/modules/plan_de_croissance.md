# Module PlanDeCroissance

**Interface utilisateur** : Partielle (via le wizard et les stratégies)

## Rôle

Plan de Croissance : **Caractère & Conviction**, **Relation & Réseau** et valeurs d'alignement ; actions, scores, principes, points de redevabilité et preuves.

> Depuis le 15/11/2026 (`docs/plan-v1.md` §6.1), l'axe **Compétence & Connaissance** relève du **Plan de Développement**. Les objectifs
> de compétence restent techniquement des `GrowthGoal` d'axe `COMPETENCE`, mais s'affichent dans le Plan de Développement.
> Le livre (chapitre 9, « Les 3 axes ») décrit encore l'ancienne version : il sera mis à jour par l'auteur.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/PlanDeCroissance/` |
| Templates | `templates/plan_de_croissance/` |
| Tests | — (à écrire) |

## Entity

`ConfirmPoint`, `Evidence`, `GrowthAction`, `GrowthGoal`, `GrowthGoalScore`, `PlanDeCroissance`, `Principle`

## Enum

`CharacterAction`, `GrowthAxis`

## Repository

`ConfirmPointRepository`, `EvidenceRepository`, `GrowthActionRepository`, `GrowthGoalRepository`, `GrowthGoalScoreRepository`, `PlanDeCroissanceRepository`, `PrincipleRepository`

## Templates

`plan_de_croissance/visualize.html.twig`
