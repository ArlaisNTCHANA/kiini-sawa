# Module Personnalite

## Questionnaires psychométriques (`src/Personnalite/Psychometrie/`)

| Questionnaire | Items | Base |
|---|---|---|
| Profil complet | 116 | IPIP-NEO-120 (Johnson 2014, domaine public), 29 facettes : facette « Libéralisme » retirée |
| Big Five essentiel | 58 | 2 items par facette |
| Type de fonctionnement | 48 | Facettes IPIP liées aux 4 axes de Jung ; 16 types aux noms KIINI SAWA |
| DISC | 24 blocs | Choix forcé (le plus / le moins), items originaux, modèle de Marston : profil naturel (le moins inversé), profil adapté (le plus), écart d'adaptation, perception croisée, interactions, indices (`DiscBank`, `DiscProfiles`, contenus tirés de la formation DISC du fondateur, avec son accord). 16 profils (`DiscCombos` : 4 marqués + 12 combinaisons) avec figures bibliques ; schémas SVG (`DiscCharts`) ; rapport `templates/personnalite/assessment/disc/` (page de garde, conclusion, conditions d'utilisation) ; accord obligatoire avant les résultats |
| Style comportemental (retiré du catalogue) | 52 | Ancien style déduit des facettes IPIP ; reste lisible pour les rapports existants |
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

## Rapports illustrés (novembre 2026)

Chaque questionnaire a un rapport complet (écran et PDF) : page de garde, schémas SVG, contenu développé, figures
bibliques (sauf Big Five), plan d'action, conclusion et page « Bon usage de ce rapport ».

- Cadre commun : `templates/personnalite/assessment/report/` (`_styles`, `_cover`, `_warnings`, `pdf`) ;
  `ReportBuilder::TEMPLATES` associe chaque questionnaire à son gabarit.
- Contenus : `DiscCombos` (16 profils DISC), `EnneagramProfiles` (9 types, ailes, flèches), `GiftProfiles` (15 dons,
  3 familles), `BigFiveProfiles` (5 dimensions × 3 niveaux), `TypeProfiles` (16 types, 4 familles).
- Schémas : `DiscCharts` (boussole, graphiques, jauge) et `ReportCharts` (figure de l'ennéagramme, barres, radar,
  jauges, préférences, grille des 16 types). SVG simples pour Dompdf (pas de text-anchor ni de marqueurs).
- L'ancien « style » (DISC déduit du Big Five) garde l'ancien rapport (`_report_body`).
- Auto-évaluations « Équilibre de Vie » et « Hygiène de Vie » : `Service/SelfAssessmentReport` + `SelfAssessmentProfiles`,
  gabarit `report/self.html.twig` (roue de la vie, priorités, domaine par domaine, plan) ; PDF sur
  `/mes-tests/{type}/resultats/pdf`.

## Textes modifiables depuis l'administration

Admin › Textes des tests (`/admin/tests/textes`, `Controller/Admin/ContentAdminController`) : l'administrateur reformule
les questions (DISC, ennéagramme, dons) et tous les textes des rapports. Le code reste la référence ; les modifications
vivent dans la table `content_override` (chemin → texte) et s'appliquent via `Psychometrie\Content::get('source')`.

- **Toujours lire les textes par `Content::get()`**, jamais directement par les constantes (`DiscCombos::PROFILES`…),
  sinon les modifications de l'administrateur ne s'affichent pas. Les constantes restent utilisées pour la structure
  (calcul, clés, couleurs).
- Verrouillé : clés, lettres, dimensions, sens et rattachement des questions, couleurs, questions IPIP.
- « Revenir au texte d'origine » supprime la ligne ; un export JSON des modifications est disponible.
