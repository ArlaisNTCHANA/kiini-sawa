# RFC-002 : L'Académie Pratique KIINI SAWA (Cours connectés aux 6 Plans)

* **Statut** : Planifié / En Roadmap (Upcoming Release — V1.2)
* **Date de conception** : Novembre 2026
* **Périmètre** : Web Symfony (`web/src/Academie/`) + API Mobile (`/api/mobile/v1/academie/...`)
* **Type de document** : Architecture Blueprint & Spécification Technique

---

## 1. Vision Métier & Différenciation

L'Académie KIINI SAWA n'est **pas** une plateforme de cours certifiants ou théoriques :
1. **Zéro certificat papier inutile** : la preuve qu'un cours a porté du fruit, c'est ce qu'il a transformé dans la vie concrète de la personne.
2. **Chaque leçon alimente un plan réel** : à la fin de chaque leçon, un exercice pratique court permet à l'utilisateur de saisir une décision, un objectif, une ressource ou un rituel qui est **directement injecté dans son vrai Plan** (*Vie, Croissance, Développement personnel, Hygiène de vie, Intendance, Transmission*, *Rituels*, *Journal* ou *Sanctuaire Couple*).
3. **Antidote à la page blanche** : l'Académie guide pas à pas les utilisateurs qui ne savent pas par où commencer dans leurs 6 plans.

---

## 2. Modèle de Données (Module `App\Academie`)

```mermaid
erDiagram
    Course ||--o{ CourseModule : "contient"
    CourseModule ||--o{ Lesson : "contient"
    User ||--o{ CourseEnrollment : "suit"
    Course ||--o{ CourseEnrollment : "inscriptions"
    CourseEnrollment ||--o{ LessonCompletion : "validations"
    Lesson ||--o{ LessonCompletion : "exercices vécus"
```

### Entités Doctrine (`web/src/Academie/Entity/`)

1. **`Course`** (Parcours pratique)
   * `id`, `slug` (unique), `title`, `subtitle`, `description`
   * `coverImage` : VARCHAR(500) nullable (visuel éditorial du parcours)
   * `primaryPlan` : enum/string (`VIE | CROISSANCE | DEVELOPPEMENT | HYGIENE | INTENDANCE | TRANSMISSION | COUPLE`)
   * `accessLevel` : `FREE | PRO | DUO` (piloté par `Entitlements`)
   * `estimatedMinutes` : INT (durée totale estimée)
   * `isPublished` : BOOL, `sortOrder` : INT
   * `createdAt`, `updatedAt`

2. **`CourseModule`** (Chapitre / Section du parcours)
   * `id`, `course_id` (FK), `title`, `sortOrder`

3. **`Lesson`** (Leçon pratique — 5 à 10 min)
   * `id`, `module_id` (FK), `slug`, `title`, `sortOrder`
   * `contentMarkdown` : TEXT (enseignement concis, sobre, lisible hors-ligne)
   * `mediaProvider` : VARCHAR(20) (`NONE | BUNNY | VIMEO | YOUTUBE | AUDIO`)
   * `mediaUrl` : VARCHAR(500) nullable (identifiant ou URL sécurisée — **jamais** de gros fichiers MP4 sur Hostinger)
   * `durationMinutes` : INT
   * **Pont vers les 6 Plans (le cœur de la RFC)** :
     * `practicePrompt` : TEXT nullable (consigne de l'exercice pratique de fin de leçon)
     * `targetPlan` : VARCHAR(30) nullable (`VIE | CROISSANCE | DEVELOPPEMENT | HYGIENE | INTENDANCE | TRANSMISSION | RITUEL | JOURNAL | COUPLE`)
     * `targetAction` : VARCHAR(40) nullable (voir table de mapping `AcademyPlanBridge` ci-dessous)
     * `practiceSchema` : JSON nullable (champs attendus par le mini-formulaire : ex. `title`, `category`, `state`, `frequency`)

4. **`CourseEnrollment`** (Suivi d'un utilisateur)
   * `id`, `user_id` (FK), `course_id` (FK) — contrainte unique `(user_id, course_id)`
   * `startedAt`, `lastActivityAt`, `completedAt` (nullable)

5. **`LessonCompletion`** (Validation + trace de l'entrée injectée)
   * `id`, `enrollment_id` (FK), `lesson_id` (FK) — contrainte unique `(enrollment_id, lesson_id)`
   * `responseData` : JSON nullable (ce que l'utilisateur a saisi dans l'exercice)
   * `createdEntityType` : VARCHAR(60) nullable (ex. `StewardshipItem`, `Habit`)
   * `createdEntityId` : INT nullable (identifiant de l'entrée créée dans le plan cible, pour permettre un lien direct *« Voir dans mon Plan d'Intendance »* et éviter les doublons en cas de re-soumission)
   * `completedAt` : DATETIME

---

## 3. Le Pont vers les 6 Plans (`AcademyPlanBridge`)

Service dédié `App\Academie\Service\AcademyPlanBridge` qui reçoit `(User $user, Lesson $lesson, array $input)` et crée ou met à jour l'entité métier réelle dans le module concerné :

| `targetPlan` | `targetAction` | Entité KIINI SAWA alimentée | Exemple concret |
| :--- | :--- | :--- | :--- |
| `VIE` | `update_purpose` | `PlanDeVie\Entity\Purpose` | Poser son Équation du But |
| `VIE` | `add_value` | `PlanDeVie\Entity\Value` | Ajouter une valeur directrice |
| `VIE` | `add_life_goal` | `PlanDeVie\Entity\LifeGoal` | Créer un objectif (Être/Faire/Avoir/Donner) |
| `CROISSANCE` | `add_growth_goal` | `PlanDeCroissance\Entity\GrowthGoal` | Choisir un trait de caractère à travailler |
| `CROISSANCE` | `add_principle` | `PlanDeCroissance\Entity\Principle` | Ajouter un principe de croissance |
| `DEVELOPPEMENT` | `add_skill_goal` | `PlanDeDeveloppement\Entity\SkillGoal` | Définir une compétence levier du trimestre |
| `DEVELOPPEMENT` | `add_learning_resource` | `PlanDeDeveloppement\Entity\LearningResource` | Ajouter un livre ou une formation ciblée |
| `HYGIENE` | `add_hygiene_domain` | `PlanDHygiene\Entity\HygieneDomain` | Poser une pratique d'hygiène mesurable |
| `INTENDANCE` | `add_stewardship_item` | `PlanDIntendance\Entity\StewardshipItem` | Inventorier une ressource (*fructifie / dort / fuit*) + décision |
| `TRANSMISSION` | `add_legacy_goal` | `PlanDeTransmission\Entity\LegacyGoal` | Définir un objectif de legs |
| `TRANSMISSION` | `add_transmission_action` | `PlanDeTransmission\Entity\TransmissionAction` | Planifier un acte concret de transmission |
| `RITUEL` | `add_habit` | `Rituels\Entity\Habit` | Créer un rituel quotidien ou hebdomadaire |
| `JOURNAL` | `add_note` | `Journal\Entity\ReflectionNote` | Enregistrer un déclic ou une réflexion |
| `COUPLE` | `add_couple_item` | `Couple\Entity\CoupleItem` | Ajouter un projet, rituel ou intention de couple |

### Règles de sécurité & intégrité du Pont
1. **Consentement visible** : le bouton indique explicitement où va l'entrée (*« Valider la leçon et ajouter à mon Plan d'Intendance »*), avec une option secondaire *« Valider la leçon sans modifier mon plan »*.
2. **Idempotence** : si l'utilisateur revient modifier son exercice sur une leçon déjà complétée, `AcademyPlanBridge` vérifie `createdEntityId` (appartenant bien à `$user`) pour **mettre à jour** l'entrée existante plutôt que de créer un doublon.
3. **Droits PRO (`Entitlements`)** : la création d'entrées dans les plans PRO respecte les mêmes règles `Entitlements::can()` que l'application principale.
4. **Zéro modification silencieuse** : une entrée existante dans le Plan de Vie (`Purpose`, `Vision`) n'est jamais écrasée sans affichage préalable du texte actuel et confirmation explicite.

---

## 4. Contrat d'API Mobile (`/api/mobile/v1/academie/...`)

* **`GET /api/mobile/v1/academie/parcours`** : catalogue des parcours filtrable par plan (`?plan=INTENDANCE`), avec progression de l'utilisateur (`completedLessons / totalLessons`).
* **`GET /api/mobile/v1/academie/parcours/{slug}`** : détail d'un parcours, modules et liste des leçons (avec statut `completed`).
* **`GET /api/mobile/v1/academie/lecons/{id}`** : contenu de la leçon + schéma de l'exercice pratique (`targetPlan`, `practicePrompt`, `practiceSchema`, `existingResponse`).
* **`POST /api/mobile/v1/academie/lecons/{id}/valider`** : valide la leçon et exécute `AcademyPlanBridge` si `applyToPlan: true` → retourne `{ completed: true, createdEntity: { plan, label, id } }`.

---

## 5. Studio Auteur (Admin), Vidéos & Expérience Design

### A. Studio de rédaction dans `/admin/academie`
Conçu pour permettre au fondateur de rédiger et publier ses cours **en totale autonomie**, sans toucher au code :
* **Gestion des parcours** : titre, accroche, visuel de couverture, plan associé, accès (`Gratuit / PRO / Duo`), bascule `Brouillon / Publié` en un clic.
* **Structure modulaire** : ajout, édition et réordonnancement simple des modules et des leçons.
* **Éditeur de leçon** : rédaction en Markdown propre (titres, citations, listes, points clés) avec **aperçu en direct**, durée estimée, et choix du pont `AcademyPlanBridge` par simple menu déroulant (*Plan cible + Action + Consigne d'exercice*).
* **Import des cours existants** : commande `php bin/console app:academie:import` pour importer les contenus de `formation/courses_dump.json` en brouillon (`isPublished = false`), prêts à être relus et reliés aux 6 plans.

### B. Stratégie Vidéo & Audio (Europe + Afrique)
Pour garantir une lecture fluide même sur connexion 3G/4G et ne jamais saturer l'hébergement Hostinger :
1. **Phase 1 — Intégration par lien (0 € au démarrage)** :
   * Collage direct d'une URL **Bunny Stream**, **Vimeo** ou **YouTube (non répertorié)** dans le formulaire de leçon.
   * Validation stricte des domaines autorisés côté serveur (anti-XSS / SSRF) et mise à jour ciblée de la directive CSP `frame-src` uniquement pour les lecteurs autorisés.
2. **Phase 2 — Upload direct `.mp4` depuis `/admin/academie`** :
   * Téléversement direct du fichier vidéo depuis l'admin vers **Bunny Stream** (transcodage HLS adaptatif 360p → 1080p automatique, coût très faible ~1–5 €/mois).
   * Stockage des capsules audio courtes (3–7 min) et fiches PDF éventuelles sur **Cloudflare R2** (0 € de bande passante).

### C. Spécifications UX & Design (Sobre, éditorial, donnant envie)
Fidèle à la charte KIINI SAWA (`#2E6B53`, `#FAF9F6`, `#B5562F`, *Playfair Display + Inter*, zéro émoji, icônes Lucide) :
* **Page Catalogue (`/academie`)** :
  * Cartes éditoriales aérées avec visuel de couverture 16:9, badge couleur du plan associé, durée estimée et barre de progression.
  * Filtres par plan (*Tous*, *Plan de Vie*, *Croissance*, *Développement*, *Hygiène*, *Intendance*, *Transmission*, *Couple*).
  * Mise en avant contextuelle du parcours correspondant au plan prioritaire de l'utilisateur.
* **Lecteur de leçon immersif (`/academie/{slug}/{lessonSlug}`)** :
  * Mise en page centrée sans distraction : lecteur vidéo/audio 16:9 net en tête de leçon.
  * Sommaire latéral repliable listant les modules et leçons avec coches de complétion.
  * Texte éditorial aéré (largeur de lecture confortable ~68 caractères par ligne).
  * **Encart « Passer à la pratique »** en pied de leçon : formulaire minimaliste connecté à `AcademyPlanBridge`, avec retour immédiat (*« Enregistré et ajouté à votre Plan d'Intendance ✓ — Voir dans mon plan → »*) et option sobre *« Valider la leçon sans modifier mon plan »*.
* **Application Mobile (`mobile-v1`)** :
  * Écrans natifs `app/academie/` reprenant la même hiérarchie épurée, avec mise en cache SQLite du texte des leçons pour lecture hors-ligne.

---

## 6. Jalons de Réalisation

| Jalon | Intitulé | Livrables |
| :--- | :--- | :--- |
| **Jalon 1** | **Socle Backend & `AcademyPlanBridge`** | Entités, migration Doctrine, service `AcademyPlanBridge` (couvrant les 6 plans + Rituels + Journal), tests unitaires et fonctionnels. |
| **Jalon 2** | **Interface Web & Admin** | Pages `/academie`, lecteur de leçon avec formulaire d'exercice connecté au plan cible, et gestion `/admin/academie` + import `formation/`. |
| **Jalon 3** | **API Mobile & Écrans `mobile-v1`** | Contrôleur `MobileAcademyController`, écrans natifs `app/academie/` et cache SQLite hors-ligne pour la lecture des leçons. |
| **Jalon 4** | **Recommandation contextuelle** | Relier `Ma Boussole 360` et les revues à l'Académie : proposer automatiquement le parcours correspondant au plan prioritaire de l'utilisateur. |
