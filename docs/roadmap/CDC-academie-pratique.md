# Cahier des Charges — L'Académie Pratique KIINI SAWA

* **Statut** : Prêt pour validation du fondateur et implémentation par lots
* **Date** : 23 novembre 2026
* **Périmètre** : Site web Symfony (`web/src/Academie/`) + Studio Auteur (`/admin/academie`) + Rappels e-mail + API & écrans mobiles (`mobile-v1`)
* **Référence architecture** : complète et remplace les détails fonctionnels de `RFC-002-academie-pratique.md`

---

## 1. Vision & positionnement (niveau cabinet d'accompagnement)

L'Académie KIINI SAWA n'est **pas** un site de cours en ligne classique (type OpenClassrooms ou Udemy) :
- **Pas de certificats, pas de notes scolaires, pas de badges gadgets.**
- **Pas de théorie déconnectée de la vie réelle.**
- **Standard visé** : celui d'un **cabinet d'accompagnement haut de gamme**, sobre, exigeant et orienté transformation mesurable. Chaque leçon de 5 à 10 minutes débouche sur une **prise de conscience** (notes), une **vérification de compréhension** (QCM de discernement) et une **décision concrète inscrite dans les vrais plans** de la personne.

### Articulation avec ce qui existe déjà dans KIINI SAWA
Pour éviter toute confusion ou doublon :
- **La Méthode** (le livre) : la référence de fond pour comprendre le *pourquoi*.
- **Le Parcours 90 jours** (`/parcours`) : le fil conducteur initial pour bâtir ses 6 plans en 13 semaines.
- **Ma Boussole 360** (`/boussole`) : le diagnostic qui révèle le plan prioritaire du moment.
- **L'Académie** (`/academie`) : des **ateliers pratiques ciblés** pour débloquer, approfondir et mettre en œuvre un plan précis (ou le Sanctuaire du Couple), avec rythme d'envoi par e-mail et mise en pratique directe dans les plans.

### Règles strictes du projet (rappel obligatoire pour l'IA qui réalise)
1. **Lexique** (`docs/lexique.md`) :
   - Les **6 plans officiels**, dans l'ordre exact : *Plan de Vie, Plan de Croissance, Plan de Développement personnel, Plan d'Hygiène de vie, Plan d'Intendance, Plan de Transmission*.
   - Le **Sanctuaire du Couple** n'est **pas** un 7e plan : il est toujours affiché séparément dans les filtres et l'interface, et toujours nommé « Sanctuaire du Couple » (jamais « Sanctuaire » seul).
   - Employer « Rituels » (jamais « Habitudes »), « Score de cohérence » (jamais « alignement »), pas d'anglicismes (« challenge », « quiz », « checklist »…).
2. **Design** (`AGENTS.md` + `DesignRulesTest`) :
   - **Zéro émoji** dans tout le code, les gabarits et les e-mails (icônes Lucide uniquement).
   - Charte calme et éditoriale (`#2E6B53`, `#FAF9F6`, `#B5562F`, *Playfair Display* + *Inter*).
   - **Vouvoiement** partout (cours, interface, QCM, e-mails).
   - **Aucun prix ni lien de paiement** dans l'application mobile (`mobile-v1`).
3. **Sécurité & qualité** :
   - CSRF sur tous les formulaires/POST web, limiteurs de fréquence sur les envois, isolement strict par utilisateur, validation stricte des URLs vidéo.
   - `doctrine:schema:validate` OK et **suite complète de tests au vert** à chaque lot.

---

## 2. Les 5 piliers de l'expérience apprenant

### Pilier 1 — Parcours modulaires & rythme d'accompagnement par e-mail
Un cours est organisé en **Modules** → **Leçons** courtes (5 à 10 min : texte éditorial aéré + vidéo/audio optionnel).

En démarrant un parcours, l'utilisateur choisit son **mode de suivi** (modifiable à tout moment) :
- **Mode Libre** : il avance à son rythme, aucun e-mail automatique.
- **Mode Rythmé** : il choisit une cadence (**1 leçon par jour** ou **3 leçons par semaine** : lundi, mercredi, vendredi).
  - La tâche quotidienne (`app:reminders:send`) envoie un e-mail sobre le matin avec le titre de la prochaine leçon non terminée, sa durée estimée et un lien direct.
  - **Règle anti-harcèlement** : si 3 e-mails consécutifs sont envoyés sans que la leçon suivante ne soit validée, le rythme passe automatiquement en pause avec un message bienveillant (réactivable en 1 clic).
  - **Liberté totale** : même en mode rythmé, l'utilisateur n'est jamais bloqué s'il souhaite lire la leçon suivante en avance.

### Pilier 2 — Questions de discernement (QCM non punitifs)
Chaque leçon peut comporter **0 à 5 questions courtes** (QCM à choix unique ou multiple) pour vérifier la compréhension réelle :
- Formulées comme des **mises en situation pratiques** plutôt que du par-cœur.
- **Aucune note scolaire, aucun blocage** : l'utilisateur valide ses réponses et reçoit immédiatement une **explication pédagogique** pour chaque question (pourquoi telle réponse est juste ou trompeuse).
- Il peut recommencer librement pour ancrer la notion.

### Pilier 3 — Carnet de notes relié aux 6 plans
Dans chaque leçon, l'utilisateur dispose d'un espace **Mes notes** sobre et rapide (en panneau latéral sur ordinateur, en onglet/accordéon sur mobile) :
- Il peut prendre autant de notes qu'il veut pendant la leçon (déclics, citations, questions personnelles).
- Chaque note est **classée sur un des 6 plans** (pré-rempli avec le plan de la leçon, modifiable en 1 clic).
- **Zéro silo mort** : l'utilisateur retrouve toutes ses notes :
  1. dans la leçon concernée ;
  2. dans un onglet **Mon carnet d'Académie** filtrable par plan (les 6 plans) et par cours ;
  3. avec un bouton sobre *« Copier dans mon Journal »* (qui crée une `ReflectionNote` dans le vrai module `Journal`).

### Pilier 4 — « Points à développer » & Pont direct vers les 6 plans (`AcademyPlanBridge`)
C'est le cœur différenciant de KIINI SAWA. En pied de chaque leçon, deux actions concrètes :

1. **L'exercice guidé de la leçon** (défini par l'auteur) :
   - Mini-formulaire ciblé qui alimente **directement l'entité réelle** du plan concerné via `AcademyPlanBridge` :

| Plan / Espace | Action (`targetAction`) | Entité KIINI SAWA alimentée |
| :--- | :--- | :--- |
| Plan de Vie | `update_purpose` / `add_value` / `add_life_goal` | `Purpose`, `Value`, `LifeGoal` |
| Plan de Croissance | `add_growth_goal` / `add_principle` | `GrowthGoal`, `Principle` |
| Plan de Développement personnel | `add_skill_goal` / `add_learning_resource` | `SkillGoal`, `LearningResource` |
| Plan d'Hygiène de vie | `add_hygiene_domain` | `HygieneDomain` |
| Plan d'Intendance | `add_stewardship_item` | `StewardshipItem` |
| Plan de Transmission | `add_legacy_goal` / `add_transmission_action` | `LegacyGoal`, `TransmissionAction` |
| Rituels | `add_habit` | `Habit` |
| Journal | `add_note` | `ReflectionNote` |
| Sanctuaire du Couple | `add_couple_item` | `CoupleItem` |

2. **Signaler un « Point à développer » libre** (disponible dans toute leçon, même sans exercice guidé) :
   - L'utilisateur peut à tout moment écrire un point personnel à travailler (*ex. « Apprendre à dire non aux sollicitations urgentes »*), choisir le plan concerné parmi les 6 plans (ou un Rituel), et l'envoyer en un clic dans ce plan.
   - Optionnellement, un lien discret lui propose d'en faire un **engagement de redevabilité** (`/social/redevabilite/nouveau`) s'il veut qu'un partenaire veille dessus.

**Garde-fous impératifs du Pont** :
- **Consentement explicite** : bouton clair (*« Enregistrer dans mon Plan d'Intendance »*) + option secondaire (*« Terminer la leçon sans modifier mon plan »*).
- **Idempotence** : modifier un exercice déjà validé met à jour l'entrée créée (`createdEntityId` vérifié comme appartenant à l'utilisateur) sans jamais créer de doublon.
- **Zéro écrasement silencieux** : pour `Purpose` ou `Vision`, toujours afficher le texte actuel avant de proposer une mise à jour.
- **Droits PRO (`Entitlements`)** : mêmes règles que le reste de l'application (un compte gratuit ne peut pas écrire dans un plan réservé à PRO).

### Pilier 5 — Recommandation intelligente via Ma Boussole 360
Puisque `BoussoleResult` est enregistré dans le compte :
- Sur `/academie`, si l'utilisateur a déjà passé sa Boussole 360, le parcours correspondant à son **plan prioritaire** est mis en avant en tête de page (*« Recommandé d'après votre Boussole 360 — priorité : Plan d'Intendance »*).
- À la fin d'un parcours, on propose de refaire sa Boussole 360 si la dernière date de plus de 30 jours.

---

## 3. Studio Auteur (`/admin/academie`)

Le fondateur doit pouvoir concevoir, rédiger, réorganiser et publier ses cours **en totale autonomie**, sans toucher au code.

### Cycle de vie d'un parcours (et des leçons)
- **Brouillon (`DRAFT`)** : invisible des utilisateurs, modifiable librement.
- **Aperçu administrateur** : bouton *« Prévisualiser comme un apprenant »* pour relire le rendu réel (vidéo, texte, QCM, exercice) avant publication.
- **Publié (`PUBLISHED`)** : visible dans `/academie` et l'API mobile selon son niveau d'accès (`FREE`, `PRO`, `DUO`).
- **Retirer pour édition** : un cours publié peut être repassé en brouillon en un clic. **Règle de sécurité** : les progressions et notes des utilisateurs déjà inscrits sont **conservées** (jamais supprimées) ; le cours disparaît simplement du catalogue public le temps de l'édition.

### Fonctionnalités du Studio
- Gestion des parcours : titre, accroche, description, image de couverture (téléversement maîtrisé ou choix local, jamais d'URL externe arbitraire), plan principal (les 6 plans, ou case séparée *Sanctuaire du Couple*), accès (`FREE | PRO | DUO`), statut (`DRAFT | PUBLISHED`), ordre d'affichage.
- Gestion des modules et des leçons : ajout, renommage, réordonnancement simple (monter/descendre).
- Éditeur de leçon :
  - Texte en Markdown propre avec **aperçu en direct** (rendu HTML assaini côté serveur contre toute faille XSS).
  - Vidéo/Audio optionnel :
    - **V1 (0 €)** : collage d'un lien **Vimeo** ou **YouTube** (converti automatiquement en `youtube-nocookie.com` côté serveur, avec validation stricte des domaines autorisés et règle CSP ciblée).
    - **V2 (optionnelle, plus tard)** : intégration **Bunny Stream** (upload direct `.mp4` et streaming adaptatif 360p–1080p) lorsque la société sera créée.
  - Bloc **Questions de discernement (QCM)** : ajout/édition de questions, choix, bonne(s) réponse(s) et texte d'explication.
  - Bloc **Exercice relié aux plans** : choix par menu déroulant du Plan cible + Action + Consigne.

---

## 4. Architecture technique recommandée (`web/src/Academie/`)

### Entités Doctrine
1. `Course` : `id`, `slug` (unique), `title`, `subtitle`, `description`, `coverImage`, `primaryPlan` (`VIE|CROISSANCE|DEVELOPPEMENT|HYGIENE|INTENDANCE|TRANSMISSION` ou `null`), `isCouple` (bool, séparé des 6 plans), `accessLevel` (`FREE|PRO|DUO`), `status` (`DRAFT|PUBLISHED`), `estimatedMinutes`, `sortOrder`, `createdAt`, `updatedAt`.
2. `CourseModule` : `id`, `course`, `title`, `sortOrder`.
3. `Lesson` : `id`, `module`, `slug`, `title`, `sortOrder`, `contentMarkdown`, `mediaProvider` (`NONE|VIMEO|YOUTUBE|AUDIO|BUNNY`), `mediaRef`, `durationMinutes`, `practicePrompt`, `targetPlan`, `targetAction`, `practiceSchema` (JSON).
4. `LessonQuestion` : `id`, `lesson`, `sortOrder`, `question`, `options` (JSON : `[{id, label}]`), `correctOptionIds` (JSON), `explanation` (TEXT).
5. `CourseEnrollment` : `id`, `user`, `course` (unique `user+course`), `paceMode` (`FREE|DAILY|THREE_PER_WEEK`), `pacePaused` (bool), `consecutiveUnopenedEmails` (int), `lastEmailSentAt`, `startedAt`, `lastActivityAt`, `completedAt`.
6. `LessonCompletion` : `id`, `enrollment`, `lesson` (unique `enrollment+lesson`), `quizAnswers` (JSON nullable), `responseData` (JSON nullable), `createdEntityType`, `createdEntityId`, `completedAt`.
7. `AcademyNote` : `id`, `user`, `lesson` (nullable), `planKey` (un des 6 plans), `kind` (`NOTE|TO_DEVELOP`), `content` (TEXT), `promotedEntityType` (nullable), `promotedEntityId` (nullable), `createdAt`, `updatedAt`.

### Contrôleurs & Services
- `AcademyService` : catalogue, accès via `Entitlements`, progression, recommandation Boussole 360.
- `AcademyPlanBridge` : création/mise à jour idempotente dans les 6 plans, Rituels, Journal, Couple.
- `AcademyPaceMailer` : appelé par `SendRemindersCommand` pour envoyer la leçon du jour aux inscrits en mode rythmé (avec pause automatique après 3 envois sans avancement).
- `AcademyController` (`/academie`, `/academie/{slug}`, `/academie/{slug}/{lessonSlug}`, `/academie/carnet`).
- `AcademyAdminController` (`/admin/academie/...`, réservé `ROLE_ADMIN`).
- `MobileAcademyController` (`/api/mobile/v1/academie/...`, routes et clés JSON en français, `no-store`, zéro prix).

---

## 5. Plan de réalisation par lots (ordre strict)

Ne pas tout coder d'un bloc. Livrer, tester (`php bin/phpunit` + `doctrine:schema:validate`) et faire valider par le fondateur à la fin de chaque lot :

| Lot | Périmètre | Critères de validation (« Fini ») |
| :--- | :--- | :--- |
| **Lot 1 — Socle & Studio Auteur** | Entités, migration, `AcademyPlanBridge`, `/admin/academie` (brouillon, aperçu, publier, retirer, modules, leçons, QCM, lien vidéo sécurisé). | Le fondateur peut créer un parcours complet, le prévisualiser, le publier et le repasser en brouillon. Tests unitaires sur `AcademyPlanBridge` (les 6 plans + idempotence + droits PRO) et sécurité XSS/CSRF. |
| **Lot 2 — Expérience Apprenant Web** | Catalogue `/academie` (filtres 6 plans + Couple séparé, recommandation Boussole 360), lecteur de leçon immersif, QCM de discernement, Carnet de notes par plan, exercice connecté au plan + « Point à développer ». | Un utilisateur parcourt une leçon de bout en bout, répond au QCM, prend une note classée par plan, et retrouve son entrée dans son vrai plan sans doublon. Suite complète au vert. |
| **Lot 3 — Parcours rythmé par e-mail** | Choix du rythme (`Libre / Quotidien / 3× semaine`), envoi branché dans `SendRemindersCommand`, pause automatique après 3 e-mails sans avancée, lien de mise en pause en 1 clic. | Tests complets sur le rythme, l'absence de doublon le même jour et la pause automatique. Test d'un vrai e-mail en production. |
| **Lot 4 — Application mobile (`mobile-v1`)** | `MobileAcademyController` (`/api/mobile/v1/academie/...`) + écrans natifs `app/academie/` + lecture texte hors connexion en SQLite. | `tsc` 0 erreur, Vitest au vert, zéro prix dans l'app, vérification visuelle sur téléphone/émulateur. |

---

## 6. Points à trancher par le fondateur avant de lancer le Lot 1

1. **Accès Gratuit vs PRO** : proposer 1 parcours découverte gratuit (ex. lié au Plan de Vie) et réserver le reste de l'Académie à PRO / Duo ?
2. **Nom dans le menu** : « L'Académie » ou « Ateliers pratiques » ?
3. **Contenu initial** : repartir de zéro dans `/admin/academie` (recommandé) plutôt que d'importer les anciens cours de janvier 2026 ?
