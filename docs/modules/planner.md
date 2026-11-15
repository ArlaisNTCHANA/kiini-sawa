# Module Planner

**Interface utilisateur** : Oui

## Rôle

Agenda : tâches, intention du jour, agrégation des habitudes, jalons et défis.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Planner/` |
| Templates | `templates/planner/` |
| Tests | — (à écrire) |

## Controller

`PlannerController`

## Entity

`PlannerTask`

## Repository

`PlannerTaskRepository`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `api_planner_events` | GET | `/api/planner/events` |
| `api_planner_get_daily_intention` | GET | `/api/planner/daily-intention` |
| `api_planner_intention` | POST | `/api/planner/intention` |
| `api_planner_task_create` | POST | `/api/planner/task/create` |
| `api_planner_task_delete` | DELETE | `/api/planner/task/{id}/delete` |
| `api_planner_task_update` | POST|PUT | `/api/planner/task/{id}/update` |
| `api_planner_toggle` | POST | `/api/planner/toggle/{type}/{id}` |
| `app_planner` | ANY | `/planner` |

## Templates

`planner/index.html.twig`
