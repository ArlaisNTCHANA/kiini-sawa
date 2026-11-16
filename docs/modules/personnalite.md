# Module Personnalite

## Questionnaires psychométriques (`src/Personnalite/Psychometrie/`)

| Questionnaire | Items | Base |
|---|---|---|
| Profil complet | 116 | IPIP-NEO-120 (Johnson 2014, domaine public), 29 facettes : facette « Libéralisme » retirée |
| Big Five essentiel | 58 | 2 items par facette |
| Type de fonctionnement | 48 | Facettes IPIP liées aux 4 axes de Jung ; 16 types aux noms KIINI SAWA |
| Style comportemental | 52 | Facettes IPIP liées au modèle DISC de Marston ; 4 styles aux noms KIINI SAWA |
| Ennéagramme | 72 | Items originaux (outil d'introspection) |
| Dons spirituels | 60 | Items originaux, 15 dons bibliques |

- Les réponses IPIP se cumulent entre questionnaires (`AssessmentService::mergedIpipAnswers`).
- Scores 0-100 (pourcentage du maximum possible) ; percentiles dès 200 profils fiables (`NormService`).
- Fidélité (alpha de Cronbach) suivie dans `/admin/tests-fidelite`.
- Qualité des réponses : réponses identiques, trop rapides ou contradictoires signalées dans le rapport.
- Marques : aucun nom protégé (16Personalities, MBTI®, DiSC®) ; une simple mention de référence, sans affiliation.
- Les anciens tests courts MBTI, DISC, Ennéagramme et Big Five sont retirés ; leurs adresses redirigent vers les nouveaux questionnaires.


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

## Résultat partageable (17/11/2026)

Privé par défaut. Depuis le rapport, « Partager mon résultat » crée un jeton (`Assessment::share()`) : la page publique
`/resultat/{jeton}` montre seulement le prénom et le résultat principal (`Psychometrie/ShareCard`), avec un bouton
« Découvrir le mien gratuitement » et des balises d'aperçu pour les réseaux. Une image (1080×1350) est dessinée dans le
navigateur et partagée avec le partage natif du téléphone (ou téléchargée). « Arrêter le partage » supprime le jeton.
Liens suivis : `utm_source=partage-test`, campagne `test-{questionnaire}`. Tests : `tests/Personnalite/SharedResultTest.php`.
