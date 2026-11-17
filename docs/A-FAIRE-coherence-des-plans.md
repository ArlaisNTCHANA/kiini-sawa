# À faire : cohérence du contenu des 5 plans (audit du 17/11/2026)

Référence : grille des 5 plans dans `docs/lexique.md` (source de code : `src/PlanDeVie/Service/PlanCatalog.php`).
Contexte et règles de travail : `AGENTS.md`, puis `docs/SUIVI-IA.md`. **Le lot 1 est validé par le fondateur** ; les lots 2
et 3 attendent ses décisions.

## Où se trouve quoi

| Sujet | Fichiers |
|---|---|
| Assistant (13 étapes) | `templates/plan_de_vie/wizard.html.twig`, `src/PlanDeVie/Controller/WizardController.php`, `src/PlanDeVie/Service/LifePlanWizardSaver.php` (`STEPS_BY_PLAN` : Plan de Vie 1-7 + 13 ; Croissance 2, 9, 10 ; Développement 8 ; Transmission 11 ; Hygiène 12) |
| Données de l'assistant | `src/PlanDeVie/Service/PlanDataProvider.php` (`getWizardData`, variables `mbtiVal`, `discVal`, `enneagramVal`, `big5Val`, `talents`…) |
| Vue d'ensemble et plans ouverts | `templates/plan_de_vie/strategies.html.twig` (cartes, en-tête du plan ouvert vers la ligne 840, onglets Intention / Axes / Lois / Système / Échéancier) |
| Résultats des tests | `src/Personnalite/Repository/AssessmentRepository.php` (`latest($user, 'disc')`…), `src/Personnalite/Psychometrie/ReportBuilder.php`, `src/Personnalite/Service/ProfileSynthesis.php` (synthèse croisée), `src/Personnalite/Service/ConfirmationService.php` (dons confirmés par les proches), `src/Personnalite/Service/SelfAssessmentReport.php` (test Hygiène de Vie : priorités) |
| Textes des tests | toujours via `App\Personnalite\Psychometrie\Content::get('source')` (modifiables dans l'admin) |

## Lot 1 : validé, à faire (≈ une demi-journée)

1. **Étape 5 « Profil de personnalité »** (`wizard.html.twig`, bloc `ETAPE 5`) : aujourd'hui, saisie manuelle de
   4 listes (type, « Style comportemental », ennéagramme, Big Five). À faire :
   - pré-remplir avec les derniers résultats des tests (`AssessmentRepository::latest` : `disc` → code DISC
     `primary.secondary`, `type` ou `profil-complet` → type 4 lettres, `enneagramme` → type, `profil-complet`/`big-five`
     → dimensions dominantes) quand le champ n'a pas encore de valeur ; afficher « Issu de votre test du … » ;
   - renommer « Style comportemental » en **DISC** et proposer les 16 profils (`DiscCombos::PROFILES`, noms via
     `Content::get('disc.profiles')`) au lieu des 8 codes actuels ; « Consciencieux » → « Conforme » (nom DISC du
     rapport) ;
   - ajouter un lien « Voir mon profil KIINI SAWA » (`path('app_synthesis')`) et « Passer les tests »
     (`path('app_personality_index')`) ; la saisie manuelle reste possible.
2. **Étape 4 « Dons & Talents »** : proposer en un clic (puces « + Ajouter ») les 3 dons du dernier test
   `dons-spirituels` (scores `top`, noms via `Content::get('gifts.gifts')`) et les dons confirmés par les proches
   (`ConfirmationService::summary(...)['confirmed']` si ≥ 3 réponses). Ajout côté client dans `#talents-container`
   (même format que `addTalent()`).
3. **Plan de Transmission** : dans l'en-tête du plan ouvert (`strategies.html.twig`, `activePlan === 'TRANSMISSION'`) et
   dans l'étape 11 de l'assistant, rappeler « Vos dons : … » (test des dons) avec un lien vers le rapport.
4. **Plan d'Hygiène de Vie** : dans l'étape 12, si le test « Hygiène de Vie » a été passé (`ProfileTest` de type
   `HYGIENE`, `SelfAssessmentReport::build()['summary']['priorities']`), proposer ces axes prioritaires comme domaines
   de départ (puces cliquables) avec la note obtenue.
5. **Balance interne dans la Croissance** : quand l'assistant est ouvert avec `plan=CROISSANCE`, ajouter en tête de
   l'étape 2 une phrase : « Votre Balance interne vient de votre Plan de Vie ; elle sert ici à choisir les convictions
   à renforcer et les croyances à transformer. »
6. **Textes** (`strategies.html.twig`, en-tête du plan ouvert) : « Pilotez l'évolution de votre Être (Être) » → retirer
   le doublon ; relire les 4 phrases d'ouverture avec la grille (questions et contenus de `PlanCatalog`).

Ensuite : tests (`tests/PlanDeVie/…` ou nouveau test fonctionnel de l'assistant : préremplissage depuis un DISC,
suggestions de dons), `php bin/phpunit`, compléter `docs/SUIVI-IA.md`, commit, déploiement (`bin/ops/envoyer-hostinger.sh`),
vérification de la prod. Le fondateur fait `git push` lui-même.

## Lot 2 : décisions du fondateur à obtenir avant de coder

- **A. « Avoir »** (finances, biens) n'a pas de plan (Être → Croissance, Faire → Développement, Donner → Transmission).
  Proposition : Plan de Développement (carrière, finances). Alternative : Hygiène de Vie (ressources).
- **B. Confirmations (chapitre 7)** : section « Confirmations » dans le Plan de Vie qui rassemble feedback nourricier,
  confirmation par les proches et preuves ; entités existantes mais inutilisées : `src/PlanDeCroissance/Entity/ConfirmPoint.php`,
  `Evidence.php` (≈ 1 jour).
- **C. Mentorat & Service** promis par la description de la Transmission mais absents de l'assistant (seuls les
  projets de legs existent) : ajouter « Personnes que j'accompagne » et « Mes services » (entités inutilisées
  `TransmissionAction`, `TalentDon`), ou corriger la description.
- **D. Axes du test Hygiène** (physique, mental, relationnel, spirituel, ressources, environnement) ≠ description du
  plan (sommeil, nutrition, sport, méditation, environnement). Proposition : Corps, Mental, Ressources, Environnement
  (le relationnel va à la Croissance, le spirituel au Plan de Vie). Attention : change les questions du test
  (`personality_question`, catégories) et la comparaison avec les passations passées.

## Lot 3 : technique (après la décision A, ≈ 1 jour)

- Les objectifs du Plan de Développement sont stockés comme `GrowthGoal` d'axe `COMPETENCE` (types `COMPETENCE` /
  `CONNAISSANCE`) dans le module Croissance (`LifePlanWizardSaver` lignes ~115-120, `PlanDataProvider` ~230-250,
  `ReminderService` ~141). Les entités propres au Développement (`src/PlanDeDeveloppement/Entity/SkillGoal.php`,
  `LearningResource.php`, `CareerStep.php`) ne sont utilisées nulle part. Migrer vers ces entités (migration de données
  + tests), en gardant les liens avec les rituels (`Habit::skillGoal`) et les objectifs de vie.
- Le livre (`docs/livre/CHAP_09_LES_3_AXES.md`, `SUMMARY_PLANS.md`) décrit l'ancienne version : ne pas le réécrire sans
  l'auteur.
