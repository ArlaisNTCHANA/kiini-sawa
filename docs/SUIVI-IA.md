# Journal de passation (pour l'IA ou le développeur qui prend la suite)

À lire après `AGENTS.md`. Ce fichier est mis à jour **à chaque chantier** : ce qui a été fait, où, pourquoi, ce qui
reste ouvert et les pièges rencontrés. Le plus récent en haut. Le détail technique de chaque module est dans
`docs/modules/*.md`.

**Règles de travail avec le fondateur** : il écrit en français, valide chaque chantier (« Yes, do it ») ; on livre,
teste (`php bin/phpunit`), commit, déploie (`bin/ops/envoyer-hostinger.sh`), vérifie la prod (page d'accueil 200,
journal `var/log/prod-AAAA-MM-JJ.log` sans erreur) ; **le fondateur fait lui-même `git push`** (refusé à l'IA).
Ne jamais afficher ni demander de mot de passe ; ne pas publier son nom (mentions légales en attente).

---

## 2026-11-17 · Historique et évolution des tests

- Les passations étaient déjà toutes conservées (`assessment`, `profile_test`), sans interface. Ajouté :
  - `Service/TestHistory` : séries `disc`, `bigfive` (profil complet + Big Five essentiel), `type`, `enneagramme`,
    `dons-spirituels`, `equilibre`, `hygiene` ; libellé du résultat principal par passation ; évolution (courbes
    `ReportCharts::lines`, lecture « ce qui a changé », tableau avant/aujourd'hui, frise des changements).
  - « Vos passations » sous chaque rapport (`templates/personnalite/_history.html.twig`), lien vers chaque ancien rapport.
  - `/mes-tests/evolution` (tests repassés) et `/mes-tests/evolution/{série}` (`SynthesisController::evolution`).
  - Auto-évaluations : consultation d'une passation précise `/mes-tests/{type}/resultats/{id}` et PDF `?id=`.
  - Liens depuis le catalogue (« Mon évolution et l'historique de mes tests ») et la synthèse (« Votre évolution »).
- **À savoir** : les scores d'une passation sont figés, mais les textes suivent le contenu actuel (`Content::get`) ;
  les réponses IPIP se cumulent entre questionnaires (voulu).
- Ce journal (`docs/SUIVI-IA.md`) a été créé à ce moment, à la demande du fondateur : **le tenir à jour à chaque chantier**.

## 2026-11-17 · Confirmation par les proches (DISC, dons)

- `ConfirmationService`, `ConfirmationController`, tables `confirmation_request` / `confirmation_answer`.
- Lien public `/confirmer/{jeton}` (déclaré public dans `security.yaml` et `SecurityGuardsTest`), sans compte, anonyme,
  champ piège, une réponse par session, 50 au plus. Résultat visible **à partir de 3 réponses** (anonymat).
- DISC : 4 descriptions sans lettre (`DiscProfiles::OBSERVED`), ordre propre à chaque lien. Dons : jusqu'à 5 cochés.
- Section `_confirmation.html.twig` dans les rapports DISC et dons (et le PDF si ≥ 3 réponses).

## 2026-11-17 · Nuances, contextes communautaires

- Intensité : `ReportBuilder::discIntensity`, `giftStrength`, netteté de l'ennéagramme, traits Big Five extrêmes.
- DISC naturel ≠ adapté : `DiscProfiles::ADAPTATION` (12 textes).
- « Dans la famille élargie et la communauté » : constantes `COMMUNITY` (DISC, ennéagramme, types).
- **Piège rencontré** : un script Python a vidé un fichier (`open(p,'w').write(open(p).read())`) : ne jamais lire et
  écrire le même fichier dans la même expression. Restauré par `git checkout`.

## 2026-11-17 · Synthèse « Mon profil KIINI SAWA », actions reliées aux plans

- `/mes-tests/synthese` : `ProfileSynthesis` croise les derniers résultats dès 2 tests (convergence sur 3 axes, zone de
  vocation DISC × famille de dons, ressources, fragilités, 3 priorités). Gratuit : carte d'identité + convergences ;
  PRO : le reste et le PDF.
- Boutons « Ajouter à mes rituels / à mon Plan de Croissance » (`TestActionService`, table `test_action`) ; rappel
  unique à 30 jours (`ReminderService::testFollowUps`, type `GOAL_DEADLINE`).

## 2026-11-17 · Refonte visuelle des rapports

- PDF : polices Inter et Playfair Display statiques (`resources/fonts/pdf`, OFL, téléchargées depuis Google Fonts avec
  l'accord du fondateur) enregistrées par `PdfFactory` (chroot sur le dossier des polices).
- Icônes SVG maison et logo : `Twig/ReportVisualsExtension` (`report_icon()`, `brand_logo()`).
- « L'essentiel en une page » + sommaire (`_essentials.html.twig`) après chaque page de garde.
- **Pièges Dompdf** : SVG simple uniquement (pas de `text-anchor`, pas de marqueurs ; largeur/hauteur du SVG égales au
  `viewBox`, sinon le dessin est réduit) ; pas de flex/grid (tableaux) ; police sans glyphe = caractère vide (d'où les
  icônes SVG au lieu de ✓ ▲ ☐).

## 2026-11-17 · Textes des tests modifiables (admin)

- Admin › Textes des tests (`/admin/tests/textes`) ; table `content_override` ; **toujours lire les textes via
  `Psychometrie\Content::get('source')`**, jamais directement les constantes (sinon les modifications ne s'affichent
  pas). Structure, calcul, couleurs et questions IPIP verrouillés.

## 2026-11-16 → 17 · Rapports illustrés (7 tests) et DISC complet

- DISC à choix forcé (`DiscBank`, 24 blocs), profil naturel (« le moins » inversé) et adapté (« le plus »), 16 profils
  (`DiscCombos`), contenus tirés de la formation DISC du fondateur (avec son accord, son nom n'apparaît pas dans l'UI).
- Rapports illustrés : cadre commun `templates/personnalite/assessment/report/` (`_cover`, `_styles`, `_warnings`,
  `pdf`), un gabarit par test (`ReportBuilder::TEMPLATES`), schémas `DiscCharts` / `ReportCharts`.
- Page « Bon usage de ce rapport » (protection juridique) et accord obligatoire avant les résultats du DISC.
- Rendu PDF de contrôle en local : scripts dans le dossier temporaire de session (`render_*.php` + `pdf2png.swift`) ;
  à recréer si besoin (amorcer le noyau en `APP_ENV=test`, rendre `report/pdf.html.twig`, convertir avec PDFKit).

## Avant le 16/11/2026

Voir `git log` : mise en ligne Hostinger, emails Brevo, audit en 6 lots, PWA, provenance, parrainage, défis,
feedback nourricier, notifications push, tableau de rétention.

## Ouvert / à faire

- Carte DISC d'équipe ou de couple pour les organisations (offre Églises).
- Suivi de la fidélité de tous les tests (Admin › Fidélité des tests couvre l'IPIP) ; normes dès 200 profils.
- Relectures humaines : théologien (figures bibliques), psychologue (textes), juriste (mentions) — côté fondateur.
- Structure juridique (15 « [À COMPLÉTER] »), coordonnées de paiement, site vitrine — côté fondateur.
