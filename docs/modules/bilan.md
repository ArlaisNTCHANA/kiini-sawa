# Module Bilan

**Interface utilisateur** : Partielle (pas de revue mensuelle)

## Rôle

Bilans et revues (scores de croissance, revue mensuelle, check-ins).

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Bilan/` |
| Templates | `templates/bilan/` |
| Tests | — (à écrire) |

## Controller

`BilanController`

## Entity

`CheckIn`, `MonthlyReview`

## Repository

`CheckInRepository`, `MonthlyReviewRepository`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `api_bilan_save_score` | POST | `/api/bilan/save-score` |
| `app_bilan` | ANY | `/bilan` |

## Templates

`bilan/index.html.twig`
