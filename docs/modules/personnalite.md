# Module Personnalite

**Interface utilisateur** : Oui

## Rôle

Tests de personnalité (DISC, MBTI, Ennéagramme, Big 5…), calcul des scores et administration du référentiel.

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Personnalite/` |
| Templates | `templates/personnalite/` |
| Tests | — (à écrire) |

## Command

`SeedPersonalityCommand`

## Controller

`PersonalityAdminController`, `PersonalityController`

## Entity

`PersonalityAnswer`, `PersonalityProfile`, `PersonalityQuestion`, `ProfileTest`

## Repository

`PersonalityAnswerRepository`, `PersonalityProfileRepository`, `PersonalityQuestionRepository`, `ProfileTestRepository`

## Service

`PersonalityScoreService`

## Routes

| Nom | Méthode | URL |
|---|---|---|
| `admin_personality_dashboard` | ANY | `/admin/tests` |
| `admin_personality_profile_delete` | POST | `/admin/tests/profile/{id}/delete` |
| `admin_personality_profile_edit` | POST | `/admin/tests/profile/{id}/edit` |
| `admin_personality_profiles` | ANY | `/admin/tests/{type}/profils` |
| `admin_personality_question_delete` | POST | `/admin/tests/question/{id}/delete` |
| `admin_personality_question_edit` | POST | `/admin/tests/question/{id}/edit` |
| `admin_personality_questions` | ANY | `/admin/tests/{type}/questions` |
| `app_personality_index` | ANY | `/mes-tests/` |
| `app_personality_result` | ANY | `/mes-tests/{type}/resultats` |
| `app_personality_submit` | POST | `/mes-tests/{type}/passer` |
| `app_personality_take` | GET | `/mes-tests/{type}/passer` |

## Templates

`personnalite/_card.html.twig`, `personnalite/_radar_chart.html.twig`, `personnalite/admin/index.html.twig`, `personnalite/admin/profiles.html.twig`, `personnalite/admin/questions.html.twig`, `personnalite/index.html.twig`, `personnalite/result.html.twig`, `personnalite/test.html.twig`
