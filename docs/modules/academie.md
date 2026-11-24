# Module Academie

**Statut** : Lot 1 (studio auteur) et Lot 2 (pages des membres) livrés le 24/11/2026. Lots 3 et 4 à venir. Cahier des charges :
`docs/roadmap/CDC-academie-pratique.md` (décisions du fondateur du 24/11/2026 ci-dessous, elles priment sur le cahier).

## Rôle

Des parcours pratiques courts (modules, leçons de 5 à 10 minutes) qui débouchent chacun sur une décision inscrite dans
les vrais plans de la personne. Pas de certificat, pas de note.

## Décisions du fondateur (24/11/2026)

- Deux niveaux d'accès seulement : **gratuit** et **PRO** (les abonnés Duo sont PRO). Un parcours de découverte gratuit
  (Plan de Vie), le reste PRO.
- Nom : **L'Académie**. Contenu : on repart de zéro (anciens cours de `../formation/` non importés).
- Un parcours repassé en brouillon quitte le catalogue ; ceux qui l'ont commencé y gardent accès, avec leur progression.
- Pas d'image envoyée : l'illustration d'un parcours est l'icône et la couleur de son plan (`AcademyPlaces::look`).
- Notes de leçon : directement dans le **Journal** (`ReflectionNote`, carnet « Académie », reliées à la leçon et au plan),
  pas de carnet séparé. Les comptes gratuits peuvent écrire ces notes même si le Journal est réservé à PRO. (Lot 2)
- Vidéos : YouTube et Vimeo seulement, chargées au clic ; politique de confidentialité complétée (section Cookies).
- L'Académie **ajoute** dans les plans, elle ne remplace jamais (ni raison d'être ni Vision).

## Emplacements

| Élément | Chemin |
|---|---|
| Code | `src/Academie/` |
| Studio auteur | `src/Academie/Controller/Admin/AcademyAdminController.php`, `templates/academie/admin/` (gabarit `admin/layout.html.twig`) |
| Corps d'une leçon (apprenant) | `templates/academie/_lesson_body.html.twig`, `_video.html.twig`, `_styles.html.twig` |
| Tests | `tests/Academie/` |
| Pages des membres | `src/Academie/Controller/AcademyController.php`, `templates/academie/index.html.twig`, `course.html.twig`, `lesson.html.twig`, `_practice.html.twig`, `_notes.html.twig`, `_course_card.html.twig` |
| URL | `/admin/academie` (studio) ; `/academie` (membres, menu « La Méthode ») |

## Entités (préfixe de table `academy_`)

| Entité | Table | Contenu |
|---|---|---|
| `Course` | `academy_course` | parcours : adresse, titre, accroche, présentation, plan (`primaryPlan`, une des 6 clés de `PlanCatalog`) ou Sanctuaire du Couple (`isCouple`, jamais les deux), accès `FREE`/`PRO`, statut `DRAFT`/`PUBLISHED`, `firstPublishedAt` |
| `CourseModule` | `academy_module` | titre, ordre |
| `Lesson` | `academy_lesson` | adresse (unique dans le parcours), titre, ordre, `visible`, texte Markdown, vidéo (`mediaProvider` `NONE`/`YOUTUBE`/`VIMEO` + identifiant seulement), durée, exercice (`targetPlan`, `targetAction`, consigne) |
| `LessonQuestion` | `academy_question` | question, réponses `[{id, label}]` (a, b, c…), bonnes réponses, explication. 5 questions et 5 réponses au plus |

| `CourseEnrollment` | `academy_enrollment` | inscription d'un membre à un parcours (unique membre + parcours), créée à la première leçon ouverte ; début, dernière activité, fin |
| `LessonCompletion` | `academy_completion` | leçon terminée (unique inscription + leçon) ; réponse à « Mettre en pratique » (JSON) et élément créé dans un plan (`createdEntityType` + `createdEntityId`) |
| `LessonNote` | `academy_lesson_note` | lien entre une note du Journal (`ReflectionNote`, supprimée avec elle) et sa leçon (mise à NULL si la leçon disparaît), avec le plan concerné |

La durée d'un parcours est calculée (somme des leçons visibles), elle n'est pas stockée.

## Studio auteur (`/admin/academie`, `ROLE_ADMIN`)

- Liste des parcours ; création (un premier module est créé avec le parcours) ; présentation, plan, accès, adresse, ordre.
- Modules : ajout, renommage, monter/descendre, suppression. Leçons : création, modification, changement de module,
  monter/descendre, masquer/rendre visible. Questions : ajout, modification, monter/descendre, suppression.
- **Publier** : refusé tant qu'il n'y a pas de leçon visible avec du texte ou une vidéo (`AcademyStudio::publishProblems`).
  **Repasser en brouillon** : ne supprime rien.
- **Supprimer** un parcours ou une leçon : seulement si le parcours n'a **jamais** été publié (`AcademyStudio::canDelete`).
  Après, on masque une leçon ou on repasse le parcours en brouillon. Une leçon créée dans un parcours publié est masquée
  par défaut.
- Aperçu « comme un apprenant » : `/admin/academie/{id}/apercu` et `/admin/academie/lecon/{id}/apercu` (bandeau
  « Aperçu administrateur », exercice inactif). Bouton « Prévisualiser le texte » pendant la rédaction
  (`POST /admin/academie/apercu-texte`).
- Toutes les modifications : POST + jeton CSRF `admin_academie` (403 sinon).

## Sécurité

- Texte : Markdown rendu côté serveur par `LessonMarkdown` (Parsedown en mode sûr : HTML brut échappé, liens
  `javascript:` neutralisés ; liens externes en nouvel onglet). Filtre Twig `lecon_markdown`.
- Vidéos : `VideoEmbed::parse` n'accepte que youtube.com, youtu.be, youtube-nocookie.com, vimeo.com, player.vimeo.com
  (hôte exact) et ne garde que l'identifiant ; l'adresse du lecteur est reconstruite (`youtube-nocookie.com`, Vimeo
  `dnt=1`). Le lecteur n'est créé qu'au clic (`_video.html.twig`). CSP : `frame-src https://www.youtube-nocookie.com
  https://player.vimeo.com` (`Shared/EventSubscriber/SecurityHeadersSubscriber`).

## Pages des membres (`/academie`, Lot 2)

- **Catalogue** (`AcademyCatalog::visibleFor`) : parcours publiés, plus ceux que le membre a commencés même s'ils sont
  repassés en brouillon (bandeau « en cours de révision »). « Mes parcours en cours », filtres par plan (`?lieu=`) : les 6
  plans, puis le **Sanctuaire du Couple séparé** par un trait. **Recommandation** : premier parcours publié du plan
  prioritaire de la dernière Boussole 360 (`AcademyCatalog::recommended`), masquée quand on filtre.
- **Parcours** : une seule action principale (Commencer / Reprendre : leçon / Découvrir PRO / parcours terminé). À la fin,
  « Refaire ma Boussole 360 » si la dernière date de plus de 30 jours ou n'existe pas. Notes du parcours rappelées en bas.
- **Leçon** : barre de progression, vidéo, texte, questions (corrigées dans le navigateur, rien n'est enregistré : pas de
  note), « Mettre en pratique », leçons précédente/suivante. Panneau **Mes notes** à droite sur ordinateur, repliable sous
  la leçon sur téléphone.
- **Accès** : parcours PRO réservé aux membres PRO (`ProRequiredException` → `/abonnement`) ; la présentation reste
  visible avec le cadenas. Inscription à la première leçon ouverte (`AcademyCatalog::enroll`).
- **Textes** : tous via `t('academie.…')`, modifiables dans Admin › Textes du site (partie « academie »).
- Toutes les actions : POST + jeton CSRF `academie` (403 sinon) ; une note ou un élément d'un autre membre n'est jamais
  touché (404 ou nouvel élément).

## Notes de leçon (`AcademyJournal`)

Une note prise dans une leçon est une vraie `ReflectionNote` (type « Déclic ») du **Journal**, dans le carnet « Académie »
(créé au besoin, recréé s'il a été supprimé), reliée à la leçon et à un des 6 plans par `LessonNote`. Permis aux comptes
gratuits même si le Journal est réservé à PRO (le lien « Ouvrir mon Journal » n'apparaît qu'à ceux qui y ont accès).
Supprimer la note dans la leçon la supprime du Journal ; la supprimer dans le Journal la retire de la leçon.

## Exercices reliés aux plans (`PracticeCatalog`, `AcademyPlanBridge`)

Liste fermée dans `PracticeCatalog::TARGETS` (cible → actions → entité visée, libellé du bouton d'accord). Le pont :

| Action | Ce qui est créé | Où le membre le voit |
|---|---|---|
| `add_value` | `Value` (catégorie « réelle ») | Plan de Vie |
| `add_life_goal` | `LifeGoal` (dimension Être/Faire/Avoir/Donner, au choix) | Plan de Vie |
| `add_growth_goal` | `GrowthGoal` axe Caractère | Plan de Croissance |
| `add_principle` | `Principle` pilier Croissance | Plan de Croissance |
| `add_skill_goal` / `add_learning_resource` | `GrowthGoal` axe Compétence, type COMPETENCE / CONNAISSANCE | Plan de Développement personnel |
| `add_hygiene_domain` | `HygieneDomain` | Plan d'Hygiène de vie |
| `add_stewardship_item` | `StewardshipItem` (domaine au choix) | Plan d'Intendance |
| `add_legacy_goal` | `LegacyGoal` | Plan de Transmission |
| `add_transmission_action` | `TransmissionAction` rattachée à un legs existant (sinon message « ajoutez d'abord un legs ») | Plan de Transmission |
| `add_habit` | `Habit` (chaque jour ou chaque semaine) | Rituels |
| `add_note` | `ReflectionNote` du carnet « Académie » (aussi parmi les notes de la leçon) | Journal |
| `add_couple_item` | `CoupleItem` (valeur, projet, thème, rituel) via `CoupleService::addItem` | Sanctuaire du Couple |

**Piège** : sur le site, le Plan de Développement personnel range compétences et connaissances parmi les objectifs du
Plan de Croissance (axe Compétence) ; les anciennes entités `SkillGoal` et `LearningResource` ne s'affichent nulle part
sur le web. Les éléments créés dans le Plan de Croissance portent `sourceType = ACADEMIE` et `sourceId` = la leçon, ce
qui les protège de l'assistant du Plan de Vie (il ne réécrit que les objectifs sans source).

Garde-fous :
- **Accord explicite** : rien n'est écrit sans le bouton (« Enregistrer dans mon Plan d'Intendance ») ; l'autre bouton
  « Terminer la leçon sans modifier mon plan » termine la leçon sans rien créer.
- **Sans doublon** : refaire l'exercice met à jour l'élément retrouvé par `createdEntityType` + `createdEntityId`, et
  seulement s'il appartient au membre (`AcademyPlanBridge::find`) ; s'il a été supprimé, un nouveau est créé.
- **Droits** (`AcademyPlanBridge::blocker`) : plan réservé à PRO (`Entitlements::PLAN_FEATURES`), Rituels PRO et limite
  gratuite de rituels, Sanctuaire du Couple ouvert (couple relié, deux comptes PRO). Le bouton disparaît et un message
  explique pourquoi ; un envoi forcé est refusé. Écrire dans le Journal depuis une leçon reste permis à tous.
- **Point à travailler** : dans une leçon sans exercice, le membre peut noter un point et choisir où l'inscrire
  (`AcademyPlanBridge::FREE_DESTINATIONS` : un objectif de chacun des 6 plans, ou un rituel). Le « Point à développer » du
  cahier des charges est ainsi fondu dans « Mettre en pratique ».

## Reste à faire (lots suivants)

- **Lot 3** : rythme par e-mail (`SendRemindersCommand`, `Notifier`, pause après 3 e-mails sans avancer).
- **Lot 4** : API `/api/mobile/v1/academie/...` et écrans `mobile-v1/app/academie/`.
