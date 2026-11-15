# Module Rituels

**Interface utilisateur** : Oui

## Rôle

Habitudes et suivi quotidien (logs, ancrage).

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Rituels/` |
| Templates | `templates/rituels/` |
| Tests | `tests/Rituels/` |

## Controller

`HabitController`

## Entity

`Habit`, `HabitLog`

## Enum

`HabitFrequency`

## Form

`HabitType`

## Repository

`HabitLogRepository`, `HabitRepository`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `api_habit_delete` | DELETE | `/api/habits/{id}` |
| `api_habit_log` | POST | `/api/habits/{id}/log` |
| `api_habit_rooting` | POST | `/api/habits/{id}/rooting` |
| `app_user_habits` | ANY | `/mes-plans/rituels` |

## Templates

`rituels/index.html.twig`
