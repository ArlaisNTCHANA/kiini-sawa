# Module PlanDeCroissance

**Interface utilisateur** : Partielle (via le wizard et les stratégies)

## Rôle

Plan de Croissance : objectifs de croissance (caractère, compétence, relation), actions, scores, principes, points de redevabilité et preuves.

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
