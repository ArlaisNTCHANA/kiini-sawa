# Module Academie

**Statut** : Lot 1 livré le 24/11/2026 (studio auteur). Lots 2 à 4 à venir. Cahier des charges :
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
| URL | `/admin/academie` (studio) ; `/academie` (membres, Lot 2) |

## Entités (préfixe de table `academy_`)

| Entité | Table | Contenu |
|---|---|---|
| `Course` | `academy_course` | parcours : adresse, titre, accroche, présentation, plan (`primaryPlan`, une des 6 clés de `PlanCatalog`) ou Sanctuaire du Couple (`isCouple`, jamais les deux), accès `FREE`/`PRO`, statut `DRAFT`/`PUBLISHED`, `firstPublishedAt` |
| `CourseModule` | `academy_module` | titre, ordre |
| `Lesson` | `academy_lesson` | adresse (unique dans le parcours), titre, ordre, `visible`, texte Markdown, vidéo (`mediaProvider` `NONE`/`YOUTUBE`/`VIMEO` + identifiant seulement), durée, exercice (`targetPlan`, `targetAction`, consigne) |
| `LessonQuestion` | `academy_question` | question, réponses `[{id, label}]` (a, b, c…), bonnes réponses, explication. 5 questions et 5 réponses au plus |

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

## Exercices reliés aux plans

Liste fermée dans `PracticeCatalog::TARGETS` (cible → actions → entité visée, libellé du bouton d'accord). Le pont qui
écrit vraiment dans les plans (`AcademyPlanBridge`) arrive au Lot 2 : il devra traiter chacune de ces actions.

## Reste à faire (lots suivants)

- **Lot 2** : `/academie` (catalogue, filtres 6 plans + Sanctuaire du Couple à part, recommandation Boussole 360), lecteur
  de leçon, inscription et progression (`CourseEnrollment`, `LessonCompletion`), réponses aux questions, notes dans le
  Journal, `AcademyPlanBridge` (accord explicite, sans doublon, droits PRO), entrée dans le menu des membres, textes via `t()`.
  Interdire alors de masquer/supprimer sans garder les progressions (déjà garanti : pas de suppression après publication).
- **Lot 3** : rythme par e-mail (`SendRemindersCommand`, `Notifier`, pause après 3 e-mails sans avancer).
- **Lot 4** : API `/api/mobile/v1/academie/...` et écrans `mobile-v1/app/academie/`.
