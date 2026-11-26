# Module Academie

**Statut** : les 4 lots sont livrés le 24/11/2026 : studio auteur, pages des membres, rythme par e-mail, application mobile. Cahier des charges :
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
  **Modifié le 24/11/2026 (soir)** : le fondateur accepte des **images dans les leçons** (envoyées dans le studio, voir « Leçons plus visuelles »). La couverture du parcours reste l'icône et la couleur du plan.
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

| `CourseEnrollment` | `academy_enrollment` | inscription d'un membre à un parcours (unique membre + parcours), créée à la première leçon ouverte ou au choix du rythme ; début, dernière activité, fin ; rythme (`paceMode` FREE/DAILY/THREE_PER_WEEK, `pacePaused`, `emailsWithoutProgress`, `lastEmailSentOn`) |
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

## Leçons plus visuelles (24/11/2026)

Demande du fondateur : les leçons étaient « très plates ». Trois ajouts au texte Markdown (`LessonMarkdown`), toujours
sans HTML brut, proposés en un clic dans l'éditeur de leçon (panneau « Rendre la leçon plus visuelle ») :

- **Encadrés** : `:::retenir` (ou `:::exemple`, `:::avous`, `:::attention`), un titre facultatif sur la même ligne, le
  texte, puis `:::`. Un type inconnu ou un encadré non fermé reste du texte.
- **Schémas dessinés** : `[[schema:code]]`, liste fermée dans `LessonRenderer::DIAGRAMS`, gabarits
  `templates/academie/schemas/<code>.html.twig` (HTML et SVG aux couleurs du site, lisibles sur téléphone). Un code
  inconnu disparaît. Onze schémas, faits pour le parcours « De la productivité à la fécondité ».
- **Images** : rubrique « Images du parcours » sur la page du parcours dans le studio (JPEG, PNG, WebP, 10 Mo au plus).
  `AcademyImages` réenregistre chaque image en WebP, 1600 pixels de large au plus (métadonnées et contenu caché
  supprimés), sous un nom aléatoire, dans `var/academie-images/<parcours>/` (hors de `public/`, que le déploiement
  remplace ; comprise dans la sauvegarde nocturne `bin/ops/sauvegarde.sh`). Servies sans connexion par
  `/academie/images/<parcours>/<nom>.webp` (`AcademyImageController`, route publique dans `security.yaml`, pour
  l'application mobile aussi). Dans le texte : `![description](/academie/images/…)` ; toute autre image est retirée.
  Suppression refusée tant qu'une leçon l'utilise ; images supprimées avec le parcours.
- `LessonRenderer` rend le tout (filtre Twig `lecon_markdown`, aperçu du studio, API mobile avec adresses d'images
  absolues). Dans l'application mobile, encadrés et schémas arrivent en HTML sans les styles du site : **à styler
  côté application** (classes `academie-encadre`, `academie-schema`, `sch-*`).
- `app:academie:import --remplacer` refuse désormais un brouillon retouché dans le studio (leçon modifiée après le
  chargement, ou images envoyées).
- `app:academie:import --textes` met à jour **seulement le texte des leçons** d'un parcours déjà chargé, même publié
  (leçons retrouvées par leur adresse : mêmes identifiants, progression et notes des membres intactes). Refusé si une
  leçon manque ou a été modifiée dans le studio après le chargement.

## Atelier de schémas (24/11/2026)

Demande du fondateur : « que le studio fasse ce que le code fait, mais en visuel ». Studio › L'Académie › **Atelier de
schémas** (`/admin/academie/schemas`, `AcademyDiagramAdminController`) : créer un schéma sans code à partir d'un
modèle, avec un **aperçu en direct** (largeur ordinateur ou téléphone), puis le placer dans une leçon par
`[[schema:<code>]]` (panneau « Rendre la leçon plus visuelle »).

- Modèles (`DiagramModels::MODELS`) : cartes (2 à 6), comparaison (2 colonnes), étapes (2 à 8), « si… alors… » (2 à 8),
  fraction (2 termes + début d'équation), cycle (3 à 6). Chaque élément : titre, texte, précision, icône (liste fermée
  `DiagramModels::ICONS`, Lucide) et couleur (liste fermée `DiagramModels::TONES`, variables de la palette).
- Entité `LessonDiagram` (table `academy_diagram`, migration `Version20261124182237`) : code (fixé à la création,
  jamais celui d'un schéma du code), titre, modèle, contenu JSON normalisé (`DiagramModels::normalize` : balises
  retirées, longueurs bornées, éléments vides ignorés, nombre d'éléments vérifié).
- Affichage : `templates/academie/schemas/_custom.html.twig` (mêmes classes `sch-*` que les schémas du code), via
  `LessonRenderer` (cherche d'abord les 11 schémas du code, puis l'atelier). Modifier un schéma le met à jour dans toutes
  les leçons ; suppression refusée tant qu'une leçon le place.
- Les dessins libres (arbre et racines, courbes) restent des schémas du code ou des images envoyées.

## Éditeur de leçon par blocs (24/11/2026)

Dans le studio, le texte d'une leçon s'édite **bloc par bloc** (mode par défaut) : paragraphe (boutons gras, italique,
lien), titre (partie ou sous-titre), liste (puces ou numérotée, un point par ligne), citation, encadré, schéma (atelier
ou dessiné dans le code), image (du parcours, avec sa description) et « texte avancé » (Markdown libre : tableaux…).
Glisser-déposer par la poignée ou flèches, « + » entre deux blocs, aperçu en direct à droite.

- `LessonBlocks` : `fromMarkdown` (texte → blocs ; tout ce qui n'est pas reconnu devient un bloc avancé, rien ne se
  perd), `toMarkdown` (blocs → texte), `sanitize` (types et valeurs en listes fermées, images du studio seulement, un
  encadré ne peut pas en fermer un autre). Le texte enregistré **garde le même format** : pages des membres, API mobile
  et chargement de fichiers inchangés. Aller-retour sans perte vérifié sur les 22 leçons du parcours livré.
- Formulaire de leçon : `editor=blocs` + `blocks` (JSON) → converti en texte par le serveur ; sinon `content` comme
  avant (le formulaire marche aussi sans JavaScript). `POST /admin/academie/blocs` convertit d'un mode à l'autre ;
  l'aperçu `apercu-texte` accepte aussi des blocs.
- « Texte (avancé) » : l'ancien champ texte, avec son panneau d'insertion, toujours disponible.

## Charger un parcours écrit dans un fichier (24/11/2026)

`php bin/console app:academie:import content/academie/<parcours>.yaml` (`src/Academie/Command/ImportCourseCommand.php`)
crée le parcours **en brouillon** : modules, leçons (Markdown), questions, exercices (cibles de `PracticeCatalog`).
`--verifier` contrôle le fichier sans rien enregistrer. Un parcours de même adresse n'est jamais écrasé, sauf
`--remplacer` sur un brouillon **jamais publié**. Ensuite, tout se relit et se publie dans le studio ; le fichier ne
sert qu'au premier chargement (les modifications du studio ne reviennent pas dans le fichier).

**Piège** : le texte des leçons garde les retours à la ligne (un retour = un saut de ligne à l'écran). Dans le fichier,
écrire chaque paragraphe sur une seule ligne ; `AcademyImportTest` refuse une phrase coupée.

Premier parcours chargé ainsi : `content/academie/de-la-productivite-a-la-fecondite.yaml`, tiré de la présentation
« Maximiser sa productivité » du fondateur (diapositives et notes d'orateur), rattaché au Plan d'Hygiène de vie, PRO.

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

## Lien avec la redevabilité (Partenaire de Redevabilité)

- Quand « Mettre en pratique » vient de créer **un rituel** ou **un objectif du Plan de Vie** (les deux choses que la
  redevabilité sait suivre), le membre reste sur la leçon (`#redevabilite`) et voit : « Voulez-vous qu'un Partenaire de
  Redevabilité veille sur ce rituel ? » avec « Choisir mon Partenaire de Redevabilité » ou « Plus tard, leçon suivante ».
- Le bouton ouvre le formulaire habituel `/social/redevabilite/nouveau?cible=HABIT:{id}` (ou `GOAL:{id}`) avec l'élément
  déjà choisi (seulement s'il appartient au membre) ; toutes les étapes restent (choix, aperçu, accord).
- Proposé seulement si le membre peut ouvrir un engagement (`AccountabilityService::canCreate` : un en gratuit) et
  qu'aucun engagement ouvert ne suit déjà cet élément (`AccountabilityService::isFollowed`).
- L'Académie reste privée : la progression dans les parcours n'est jamais montrée au Partenaire de Redevabilité, et le
  rythme par e-mail ne le prévient jamais.

## Rythme par e-mail (Lot 3, `AcademyPaceMailer`)

- **Choix** : carte « Mon rythme » sur la page du parcours (`POST /academie/{slug}/rythme`) : à mon rythme sans e-mail
  (par défaut), une leçon par jour, ou trois par semaine (lundi, mercredi, vendredi). Choisir un rythme inscrit au
  parcours ; rechoisir relance un rythme en pause.
- **Envoi** : chaque matin par `app:reminders:send` (`SendRemindersCommand` appelle `AcademyPaceMailer::sendAll`) : la
  prochaine leçon non terminée, sa durée, un lien direct (gabarit `templates/academie/email/lecon.html.twig`, textes
  `t('academie.email.…')`). Type de notification `ACADEMY_LESSON` : e-mail seulement (pas dans le centre de
  notifications), comme l'accueil. Rien pour un parcours terminé, un rythme libre ou en pause, un parcours PRO quand le
  membre ne l'est plus.
- **Pas de doublon** : date du dernier envoi (`lastEmailSentOn`) + clé de notification `academie-{inscription}-{date}`.
- **Pause automatique** : après 3 e-mails sans leçon terminée (`emailsWithoutProgress`, remis à zéro à chaque leçon
  terminée), le 4e jour part un message bienveillant (`email/pause.html.twig`) et le rythme passe en pause. Reprise en un
  clic : bouton « Reprendre mon rythme » sur la page du parcours (le lien de l'e-mail y mène).
- **Mettre en pause depuis l'e-mail** : le lien de désinscription signé (et l'en-tête `List-Unsubscribe`) du type
  `ACADEMY_LESSON` met en pause tous les rythmes du membre (`AcademyPaceMailer::pauseAll`, appelé par
  `NotificationController::unsubscribe`).
- Le membre n'est jamais bloqué : il peut lire les leçons suivantes en avance.

## Application mobile (Lot 4)

- API `/api/mobile/v1/academie/...` (`Mobile/Controller/MobileAcademyController`, contrat dans `docs/api-mobile.md` § 15).
  La logique d'une leçon (« Mettre en pratique », proposition d'un Partenaire de Redevabilité) est dans
  `AcademyLearning`, partagé par le site et l'application : une seule règle pour les deux.
- Écrans `mobile-v1/app/academie/` : catalogue (`index.tsx`), parcours (`[slug]/index.tsx`, rythme compris), leçon
  (`[slug]/[lecon].tsx`). Texte rendu bloc par bloc (`src/academie/lecture.ts`, aucun HTML interprété), vidéo ouverte dans
  le navigateur, questions corrigées sur le téléphone, notes et exercice envoyés au serveur. Lecture hors connexion :
  catalogue, parcours et leçons ouverts sont gardés dans le stockage local (`academie_…`). Entrée « L'Académie » dans le
  menu, rubrique « La Méthode ». Aucun prix.
- Créer un engagement de redevabilité reste sur le site (comme ailleurs dans l'application) : le bouton ouvre le
  formulaire du site avec l'élément déjà choisi.

## Vignettes et page de présentation des parcours (25/11/2026)

Demande du fondateur : « que les parcours s'affichent avec une image vignette ; quand on clique, une description, les
plans couverts et le programme ; le plus visuel possible ». Maquette validée (captures `../captures-academie-vignettes/`).

- **Données** (`Course`) : `coverImage` (nom d'une image du studio du parcours, `AcademyImages`, WebP), `coverWord`
  (mot écrit sur la vignette dessinée), `extraPlans` (plans ajoutés à la main, clés de `PlanCatalog` seulement).
  Migration `Version20261125061749`.
- **Vignette** : `templates/academie/_vignette.html.twig`. Image du studio si elle existe encore
  (`AcademyImages::coverUrl`), sinon composition CSS aux couleurs du plan principal, avec son icône Lucide et le mot :
  aucune image externe. Étiquette Gratuit / PRO.
- **Plans couverts** (`AcademyOverview`) : plan principal + plans des exercices des leçons visibles + plans ajoutés,
  dans l'ordre du livre. Rituels et Journal comptés à part (« dans votre quotidien »). Le Sanctuaire du Couple est
  toujours à part, avec son nom complet (parcours du couple ou exercice vers le Sanctuaire du Couple). Aussi : chiffres
  (leçons, minutes, questions, exercices, vidéos), programme numéroté (`programme()`), activités d'une leçon.
- **Catalogue** : cartes `_course_card.html.twig` (vignette, plan, titre, accroche, durée, leçons, accès, progression,
  pastilles des plans). Grille `academie-grid` (une colonne sur téléphone).
- **Page d'un parcours** : `_presentation.html.twig`, partagée par la page des membres et l'aperçu du studio
  (`preview: true`). Bandeau, une seule action principale (Commencer / Reprendre / PRO / terminé) avec progression et
  prochaine leçon, rythme, « En quelques mots » + chiffres, « Les plans que ce parcours fait avancer » (plan principal
  mis en avant, les 6 plans dont les non abordés estompés), programme (étapes numérotées, leçons avec vidéo, questions,
  exercice et sa destination, faites cochées, prochaine entourée d'or), notes.
- **Studio** : encadré « Vignette et plans couverts » de la fiche du parcours (route `admin_academie_cover`, CSRF) :
  envoyer une image (mêmes contrôles que les images des leçons), ou en choisir une du parcours, ou revenir à la vignette
  dessinée ; mot ; plans à ajouter (les plans automatiques sont cochés et grisés) ; aperçu de la carte en direct. Une
  image qui sert de vignette ne peut pas être supprimée.
- **Fichier YAML** : `cover: fichier.jpg` (image locale à côté du fichier, jamais une adresse internet, 10 Mo au plus),
  `cover_word: …`, `plans: [VIE, …]`. Vérifié par `--verifier`.
- **Mobile** : `vignette`, `motVignette`, `plansCouverts` dans le résumé ; `sixPlans`, `quotidien`, `chiffres`, et pour
  chaque leçon `questions` et `exercice` (voir `docs/api-mobile.md`). Écrans `mobile-v1/app/academie/` (vignette dessinée
  en SVG, `adresseVignette` n'accepte que les images du studio).
- Tests : `tests/Academie/AcademyCoverTest.php`, `tests/Mobile/MobileAcademyTest.php`, `mobile-v1/__tests__/academie.test.ts`.

## Parcours de plan : remplir ses plans séance après séance (26/11/2026, lot 1)

Décisions du fondateur : `docs/PROPOSITION-parcours-guides.md` § 9. Un **parcours de plan** est un parcours de
l'Académie d'un genre particulier (`Course::kind` = `PLAN`, `THEME` par défaut ; migration `Version20261126070037`),
rattaché à l'un des 6 plans. Choisi dans le studio (« Genre de parcours ») ou par `kind: PLAN` dans un fichier YAML.

- **Séances qui écrivent une rubrique entière** : actions `write_purpose`, `list_convictions`, `list_values`,
  `list_passions`, `list_talents`, `write_vision`, `list_life_goals` (cible `VIE` de `PracticeCatalog`), traitées par
  `PlanRubrics` et non par `AcademyPlanBridge`. L'enregistrement passe par `LifePlanWizardSaver` (mêmes champs que
  l'assistant). Comme l'assistant supprime ce qui n'est plus envoyé, la séance renvoie toujours les éléments existants
  (identifiant + texte, échéances des objectifs comprises) : elle **ajoute** sans rien retirer ni modifier. Raison d'être
  et Vision (un seul texte) sont montrées préremplies : la personne les reprend elle-même. **À confirmer avec le
  fondateur** : la règle « l'Académie ne remplace jamais raison d'être ni Vision » est ainsi assouplie pour les seuls
  parcours de plan, la modification étant faite par la personne, texte sous les yeux.
- **Méditer** : encadré `:::mediter` (`LessonMarkdown::CALLOUTS`) avec une case « J'ai pris ce temps » (aucune mesure,
  rien d'enregistré, pas de minuteur).
- **Ligne de métro** : `templates/academie/_metro.html.twig`, en tête des séances (à la place de la barre de progression)
  et sur la page du parcours : stations faites, du jour, à venir, temps restant.
- **Célébration** : après une séance, message « Posé dans votre Plan de Vie : … » et « Station N sur M franchie,
  prochaine séance… » ; à la fin du parcours, redirection vers la page du plan (`AcademyController::celebrate`).
- **Rythme** : une séance par jour par défaut à l'inscription (`CourseEnrollment::__construct`), modifiable.
- **Où on les propose** : section « Remplir mes plans, séance après séance » en tête de `/academie` ; page
  `/academie/par-ou-commencer` (parcours de plan, parcours de 90 jours, « Je remplis seul ») ; « Ma prochaine étape »
  (`NextStep`, règle 0 : « Par où commencer ? » tant qu'aucun parcours de plan n'a été commencé et que le Plan de Vie
  n'est pas complet, puis « Reprendre » ; pas d'invitation au parcours de 90 jours pendant un parcours de plan) ; carte
  « Bravo » de fin d'accueil (bouton « Choisir mon parcours guidé »). **Rien de tout cela n'apparaît tant qu'aucun
  parcours de plan n'est publié.**
- **Contenu** : `content/academie/parcours-plan-de-vie.yaml` (« Mon Plan de Vie », 8 séances, gratuit), premier jet de
  l'IA : extraits du livre recopiés mot pour mot (chapitres 4 à 10, vérifiés par programme), prières d'une ligne à relire.
  Charger : `php bin/console app:academie:import content/academie/parcours-plan-de-vie.yaml`, puis relire et publier
  dans le studio. Les adresses des séances viennent des titres (une clé `slug` par leçon n'est pas lue).
- **Mobile** : la pratique d'une séance arrive en `type: seance` (`rubrique` : genre, libellé, texte, éléments,
  objectifs) ; l'envoi accepte `texte`, `horizon`, `elements`, `objectifs`. Les écrans de l'application ne l'affichent pas
  encore.
- **Lot 2 (26/11/2026)** : e-mail du rythme propre aux parcours de plan (`email/seance.html.twig`, objet
  « Aujourd'hui : … », station, ce qui a été posé la dernière fois, verset tiré de l'encadré `:::mediter`,
  « Commencer la séance ») ; **brouillon** d'une séance enregistré pendant la frappe (`POST …/brouillon`, colonne
  `academy_enrollment.drafts`, migration `Version20261126081226`), repris à l'ouverture même sur un autre appareil,
  effacé à la fin de la séance, jamais écrit dans le plan ; le résultat de la Boussole propose le parcours de plan publié.
- **Lot 3 (26/11/2026)** : séances pour les cinq autres plans (`PlanRubrics::RUBRICS`, clé `plan`) :
  `list_character_goals`, `list_relation_goals` (Croissance), `list_skills`, `list_knowledge` (Développement : la même
  étape 8 enregistre les deux listes, la liste sœur est toujours renvoyée), `list_hygiene_domains`, `list_stewardship`
  (Intendance : pas d'étape d'assistant, éléments créés directement, une ligne par domaine), `write_eternity` (le
  déplacement de trésor, enregistré par la même étape, est renvoyé tel quel), `list_legacy_goals`. Pour les blocs de
  l'assistant (genre « indexed »), les éléments existants sont renvoyés avec leur identifiant **et leurs objectifs du
  Plan de Vie soutenus** (sinon l'assistant retire ces liens) ; stratégie et statut ne sont pas envoyés, donc gardés.
  Contenus (premiers jets, PRO) : `content/academie/parcours-plan-de-croissance.yaml` (3 séances),
  `…-de-developpement.yaml`, `…-d-hygiene.yaml`, `…-d-intendance.yaml`, `…-de-transmission.yaml` (2 séances chacun),
  extraits des chapitres 13 à 20 vérifiés mot pour mot.
- Tests : `tests/Academie/PlanRubricsTest.php`.

## Reste à faire

- Essai d'un vrai e-mail de leçon en production (après déploiement, avec l'accord du fondateur).
- Affichage des écrans mobiles dans un simulateur ou sur un téléphone (pas fait : il faut un compte connecté).
