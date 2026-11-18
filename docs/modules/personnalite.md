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

## Synthèse « Mon profil KIINI SAWA » et actions reliées aux plans

- `/mes-tests/synthese` (`SynthesisController`, `Service/ProfileSynthesis`) : croise les derniers résultats (DISC, Big Five,
  type, ennéagramme, dons, équilibre de vie) dès 2 tests. Carte d'identité, convergence des tests sur 3 axes
  (personnes/tâche, rythme, structure ; `ReportCharts::convergence`), zone de vocation (dimension DISC × famille de dons,
  `SynthesisContent::VOCATIONS`, modifiable dans l'admin), ressources et fragilités, 3 priorités. Gratuit : carte
  d'identité et convergences ; PRO : le reste et le PDF.
- Boutons « Ajouter à mes rituels / à mon Plan de Croissance » sous chaque action des rapports (`_add_action`,
  `TestActionService`) : rituel hebdomadaire de 30 jours ou objectif (axe Caractère, échéance 30 jours). Table
  `test_action` ; `ReminderService::testFollowUps` relance une fois à 30 jours (« Où en êtes-vous ? »).
- PDF : polices Inter et Playfair Display (`resources/fonts/pdf`, OFL) enregistrées par `PdfFactory` ; icônes et logo par
  `Twig/ReportVisualsExtension` (`report_icon()`, `brand_logo()`) ; « L'essentiel en une page » (`_essentials`).

## Nuances, contextes et regard des proches

- Intensité : netteté DISC (`ReportBuilder::discIntensity`), niveau des dons (`giftStrength`), netteté du type
  d'ennéagramme, traits Big Five extrêmes (≤ 20 ou ≥ 80).
- DISC : lecture combinée quand la dimension adaptée diffère de la naturelle (`DiscProfiles::ADAPTATION`, 12 cas).
- Famille élargie et communauté : `DiscCombos::COMMUNITY`, `EnneagramProfiles::COMMUNITY`, `TypeProfiles::COMMUNITY`.
- Confirmation par les proches (DISC, dons) : `ConfirmationService`, `ConfirmationController`, tables
  `confirmation_request` / `confirmation_answer`. Lien public `/confirmer/{jeton}` (sans compte, anonyme, champ piège,
  une réponse par session, 50 au plus) ; descriptions DISC sans lettre (`DiscProfiles::OBSERVED`) ; résultat dans le
  rapport à partir de 3 réponses ; notification `FEEDBACK_RECEIVED` à chaque réponse.

## Historique et évolution

`Service/TestHistory` (séries, libellés, évolution) ; « Vos passations » sous chaque rapport ; `/mes-tests/evolution`
et `/mes-tests/evolution/{série}` ; passation précise d'une auto-évaluation : `/mes-tests/{type}/resultats/{id}`.
Tests : `tests/Personnalite/HistoryTest.php`.

## Lectures croisées (InsightEngine, 18/11/2026)

`App\Personnalite\Service\InsightEngine` confronte les résultats de plusieurs tests et produit jusqu'à 6 « lectures » classées (vigilance,
tension, paradoxe, convergence, appel, croissance). Ce n'est ni un score ni de l'apprentissage : **chaque règle est écrite, nommée et
testée** (`tests/Personnalite/InsightEngineTest.php`). Règles actuelles : adaptation excessive (écart naturel/adapté ≥ 25, renforcée par une
sensibilité émotionnelle élevée), motivation (ennéagramme) contre comportement (DISC) via `ENNEAGRAM_EXPECTS` (lecture usuelle, **non
démontrée**, présentée comme hypothèse), don public et tempérament réservé, don discret et tempérament ouvert, sensibilité et don de soin,
franchise et don de soin, fiabilité et don concret, curiosité et don de pensée, signature sous pression (DISC + ennéagramme), chemin de
croissance, cohérence ou divergence des trois axes, terrain de vie (équilibre de vie).
Chaque lecture cite ses preuves (quel test dit quoi), un niveau de confiance (nombre de tests qui la recoupent), une question et un premier
pas relié à un plan. Affichage : page « Lectures croisées » de la synthèse (PRO) et encart « Ce que vos autres tests ajoutent » dans
chaque rapport (PRO ; un test gratuit voit seulement le nombre de lectures disponibles).
**Limites assumées :** seuils posés à la main, pas validés sur des données réelles ; à recalibrer avec de vrais résultats d'utilisateurs.
**Pour ajouter une règle :** une méthode privée qui renvoie `$this->insight(...)` ou `null`, l'ajouter à `analyze()`, un test.

**Lectures internes (même jour) :** `BIGFIVE_PAIRS` (7 rapprochements de facettes : autorité sans foule, chaleureux mais intimidé, ambition et inquiétude, audace réfléchie, cœur tendre et voix franche, idées et finitions, irritation) et `GIFT_PAIRS` (7 paires de dons : enseigner en prenant soin, compassion lucide, vérité et tendresse, porte ouverte, générosité concrète, foi qui prie, conduire avec sagesse). Elles sont marquées `internal` (« Lu dans ce seul test : à recouper »), pèsent moins que les lectures croisées et n'apparaissent dans la synthèse que si elles dépassent les autres ; chaque rapport en montre au plus une, après ses lectures croisées.
**Zone de vocation :** `ProfileSynthesis::vocationNuances()` précise comment l'exercer selon le tempérament (Big Five : ressourcement, attrait, rythme d'engagement, vigilance) ; la zone elle-même reste DISC × famille de dons.

**Regard des proches (18/11/2026) :** `InsightEngine::analyze(..., $close)` reçoit les synthèses agrégées de `ConfirmationService` (DISC et dons, **trois réponses au moins**, jamais de réponse individuelle). Lectures : proches qui reconnaissent la nature (`close-confirm`), qui ne voient que le profil adapté (`close-adapted`), qui voient autre chose (`close-other`, paradoxe) ; dons reconnus, non reconnus, ou vus par les autres seulement. Une lecture appuyée par les proches porte la source `proches` et la mention « Croise vos tests et le regard de vos proches ». La lecture « adaptation excessive » est renforcée si les proches ne voient que le profil adapté. La synthèse invite à demander ce regard quand il manque (`closeInvites`).

## Fiches de personnages bibliques (pilote, 18/11/2026)

`App\Personnalite\Psychometrie\BiblicalFigures` : fiches complètes (Élie, Joseph de Nazareth, Timothée) affichées dans le rapport DISC à la place des deux phrases d'avant (`templates/personnalite/assessment/report/_figure_sheet.html.twig`). **Cadre doctrinal du fondateur : le Christ seul et son œuvre, l'Ancien Testament pointe vers le Christ, la grâce et jamais le légalisme ; version : Louis Segond 1910.** Chaque fiche sépare « Dans le texte » (récit, références, citations), « Notre lecture » (tempérament, dit comme lecture, pas comme affirmation du texte), « Là où il est fragile », « Comment Dieu le relève » et « Le Christ au centre ».
**Vérification des citations :** `bin/outils/bible.py` lit `var/bible/lsg.json` (non versionné, non déployé ; source : github.com/midvash/bible-data, domaine public, **à contrôler : `sourceVerified: false` dans ses métadonnées**, les extraits sont recoupés avec des versets connus) ; `BiblicalFiguresTest` vérifie chaque citation (ignoré si le fichier est absent). Les fiches portent `reviewed => false` et l'écran/PDF affiche « Fiche en cours de relecture » **tant que le fondateur n'a pas validé** ; passer `reviewed` à `true` après relecture.
**Reste :** relecture des 3 fiches par le fondateur ; puis bibliothèque d'environ 35 personnages et rattachement aux autres profils (DISC : 16 profils ; ennéagramme, dons, type).
