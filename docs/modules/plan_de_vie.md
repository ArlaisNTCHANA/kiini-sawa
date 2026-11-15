# Module PlanDeVie

**Interface utilisateur** : Oui

## Rôle

Plan de Vie : raison d'être, vision, valeurs, passions, balance interne, objectifs de vie et jalons.
Héberge aussi ce qui est commun aux 5 plans :

- `PlanCatalog` : nom, route et gabarit de chaque plan (lexique de la charte §1bis) ;
- `PlanDataProvider` : données des pages, visualisations, emails et de l'assistant ;
- `LifePlanWizardSaver` : enregistrement **incrémental** de l'assistant (`/plan-de-vie/assistant`). Chaque ligne envoyée porte l'identifiant de l'élément (`<champ>_ids[]` ou `<champ>_id[i]`) : l'élément est mis à jour, pas recréé, et ses liens sont conservés. Chaque clic sur « Suivant » enregistre l'étape (requête `autosave`) ;
- `AbstractPlanController` : visualisation, envoi par email, PDF, hub ouvert sur un plan. Chaque plan a son contrôleur dans son module ;
- `PlanPdfRenderer` : export PDF (Dompdf) du Plan de Vie et du Plan de Croissance.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/PlanDeVie/` |
| Templates | `templates/plan_de_vie/` |
| Tests | `tests/PlanDeVie/` |

## Controller

`PlanDeVieController` (+ `Admin/PlanDeVieController`), `StrategiesController`, `WizardController`, `AbstractPlanController`

## Entity

`InternalBalance`, `LifeGoal`, `LifeMilestone`, `Passion`, `PlanDeVie`, `PlanHistory`, `Purpose`, `Season`, `Value`, `Vision`

## Form

`PurposeType`, `VisionType`

## Repository

`InternalBalanceRepository`, `LifeGoalRepository`, `LifeMilestoneRepository`, `PassionRepository`, `PlanDeVieRepository`, `PlanHistoryRepository`, `PurposeRepository`, `SeasonRepository`, `ValueRepository`, `VisionRepository`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `admin_plan_de_vie` | GET | `/admin/plan-de-vie` |
| `admin_plan_de_vie_add_goal` | POST | `/admin/plan-de-vie/goal/add` |
| `admin_plan_de_vie_add_internal_balance` | POST | `/admin/plan-de-vie/internal-balance/add` |
| `admin_plan_de_vie_add_milestone` | POST | `/admin/plan-de-vie/milestone/{goalId}/add` |
| `admin_plan_de_vie_add_passion` | POST | `/admin/plan-de-vie/passion/add` |
| `admin_plan_de_vie_add_value` | POST | `/admin/plan-de-vie/value/add` |
| `admin_plan_de_vie_delete_goal` | POST | `/admin/plan-de-vie/goal/{id}/delete` |
| `admin_plan_de_vie_delete_internal_balance` | POST | `/admin/plan-de-vie/internal-balance/{id}/delete` |
| `admin_plan_de_vie_delete_milestone` | POST | `/admin/plan-de-vie/milestone/{id}/delete` |
| `admin_plan_de_vie_delete_passion` | POST | `/admin/plan-de-vie/passion/{id}/delete` |
| `admin_plan_de_vie_delete_value` | POST | `/admin/plan-de-vie/value/{id}/delete` |
| `admin_plan_de_vie_edit_goal` | POST | `/admin/plan-de-vie/goal/{id}/edit` |
| `admin_plan_de_vie_edit_internal_balance` | POST | `/admin/plan-de-vie/internal-balance/{id}/edit` |
| `admin_plan_de_vie_edit_passion` | POST | `/admin/plan-de-vie/passion/{id}/edit` |
| `admin_plan_de_vie_edit_value` | POST | `/admin/plan-de-vie/value/{id}/edit` |
| `admin_plan_de_vie_toggle_milestone` | POST | `/admin/plan-de-vie/milestone/toggle/{id}` |
| `admin_plan_de_vie_update_purpose` | POST | `/admin/plan-de-vie/purpose` |
| `admin_plan_de_vie_update_vision` | POST | `/admin/plan-de-vie/vision` |
| `app_user_contribution_plan_send_email` | POST|GET | `/mes-strategies/contribution/email` |
| `app_user_contribution_plan_visualize` | ANY | `/mes-strategies/contribution/visualiser` |
| `app_user_croissance_plan_send_email` | POST|GET | `/mes-strategies/croissance/email` |
| `app_user_croissance_plan_visualize` | ANY | `/mes-strategies/croissance/visualiser` |
| `app_user_developpement_plan_send_email` | POST|GET | `/mes-strategies/developpement/email` |
| `app_user_developpement_plan_visualize` | ANY | `/mes-strategies/developpement/visualiser` |
| `app_user_discipline_plan_send_email` | POST|GET | `/mes-strategies/discipline/email` |
| `app_user_discipline_plan_visualize` | ANY | `/mes-strategies/discipline/visualiser` |
| `app_user_life_plan` | ANY | `/life-plan` |
| `app_user_life_plan_send_email` | POST|GET | `/mes-plans/vie/email` |
| `app_user_life_plan_visualize` | ANY | `/mes-plans/vie/visualiser` |
| `app_user_life_plan_wizard` | ANY | `/mes-plans/vie/wizard` |
| `app_user_strategies` | ANY | `/mes-strategies` |
| `app_user_strategies_croissance_axis_save` | POST | `/mes-strategies/croissance/axis/save/{sourceType}/{sourceId}` |
| `app_user_strategies_croissance_principles_save` | POST | `/mes-strategies/croissance/principles/save` |
| `app_user_strategies_principles_save` | POST | `/mes-strategies/{plan}/principles/save` |

## Templates

`plan_de_vie/admin/index.html.twig`, `plan_de_vie/index.html.twig`, `plan_de_vie/strategies.html.twig`, `plan_de_vie/visualize.html.twig`, `plan_de_vie/wizard.html.twig`
