# Journal de passation (pour l'IA ou le développeur qui prend la suite)

À lire après `AGENTS.md`. Ce fichier est mis à jour **à chaque chantier** : ce qui a été fait, où, pourquoi, ce qui
reste ouvert et les pièges rencontrés. Le plus récent en haut. Le détail technique de chaque module est dans
`docs/modules/*.md`.

**Règles de travail avec le fondateur** : il écrit en français, valide chaque chantier (« Yes, do it ») ; on livre,
teste (`php bin/phpunit`), commit, déploie (`bin/ops/envoyer-hostinger.sh`), vérifie la prod (page d'accueil 200,
journal `var/log/prod-AAAA-MM-JJ.log` sans erreur) ; **le fondateur fait lui-même `git push`** (refusé à l'IA).
Ne jamais afficher ni demander de mot de passe ; ne pas publier son nom (mentions légales en attente).

---

## 2026-11-17 · Lot C (début) : le cercle des plans vivant

- **« Mes plans »** : la figure fixe des 6 plans est remplacée par un cercle SVG interactif
  (`plan_de_vie/_plans_circle.html.twig`) : chaque plan est un lien, entouré d'un **anneau de progression** ; survol, focus
  clavier ou premier toucher (mobile) affichent ses étapes et un bouton « Prochaine étape ». Plans PRO verrouillés : lien
  vers l'abonnement. Couleurs `--plan-*`.
- **`PlanDeVie\Service\PlanProgress`** : 3 ou 4 étapes simples par plan, tirées du livre (écrire, relier à un objectif,
  évaluer, rendre compte). Ce n'est pas un score de qualité : « 100 % » = étapes de départ franchies. Pour changer les étapes
  d'un plan, modifier ce seul fichier ; `tests/PlanDeVie/PlanProgressTest.php` vérifie les routes.
- Données ajoutées par `AbstractPlanController::renderStrategies` (`planProgress`).
- **Photo** : le fondateur ne veut pas de photo de lui sur la vitrine (mémoire `kiini-no-personal-photo`).
- **Reste (lot C)** : accueil « Ma prochaine étape » (réutiliser `PlanProgress` + parcours + bilan du jour), page « Mes
  rythmes », une action principale par écran ; lot B (composants uniques).

## 2026-11-17 · Lot A de l'audit design : zéro émoji, couleurs, casse

- **Zéro émoji dans l'interface** : 208 pictogrammes remplacés par des icônes Lucide (`<i data-lucide="…" class="ks-ico">`,
  style `.ks-ico` dans `app.css`) dans les textes, retirés dans les attributs, scripts, pages imprimables et libellés de
  carte. Messages PHP nettoyés. Restent permis : ✓ ✗ → ← (signes typographiques) et les sorties console.
- **Garde-fou** : `tests/Shared/DesignRulesTest.php` refuse tout émoji dans `templates/` et `src/` (hors `Command/` et emails)
  et l'ancien turquoise. Pour une icône : `<i data-lucide="nom" class="ks-ico" aria-hidden="true"></i>`.
- **Couleurs** : bandeau d'accueil en vert forêt avec halos sauge et or (plus de bleu ni d'indigo) ; teintes vives restantes
  (bleu, indigo, violet, rose, ambre) ramenées à la palette du livre dans les écrans de l'application. Les **rapports de
  tests** (DISC, ennéagramme…) et les pages « visualiser » gardent leurs couleurs propres, voulues.
- **Casse française** : « Balance interne », « Objectifs spécifiques », « Réseau de croissance »… (le livre garde les titres
  « X & Y » en majuscules : conservés). « Défis & Challenges Collectifs » → « Défis collectifs ».
- **Bug corrigé** : la page d'administration « Entonnoir » (déployée le 17/11) utilisait un filtre Twig `|min` inexistant et
  aurait donné une erreur 500 ; corrigé (`min()`), test de rendu ajouté. **À déployer.**
- **Reste de l'audit** : lot B (un seul modèle de bouton, d'en-tête, de carte ; styles en ligne), lot C (accueil « Ma
  prochaine étape », « Mes rythmes », une action principale par écran), visuels interactifs.

## 2026-11-17 · Audit design n° 2 : noms, vitrine, entonnoir

Audit : `docs/audit-design-2-strategique-2026-11-17.md` (vitrine, premier usage, interactivité, noms).
- **Noms** : « Mes plans » partout. `/mes-plans` (route `app_plans`, `PlansOverviewController`, gabarit `plans.html.twig`) ;
  `/mes-strategies` redirige en 301 (anciens courriels). Le menu choisit la rubrique par le **préfixe le plus long**
  (`/mes-plans/rituels` reste dans « Mon quotidien »). « Stratégie » est réservé au sens du livre.
- **Vitrine** (`dashboard/landing.html.twig`) : titre « Bâtissez la vie pour laquelle Dieu vous a créé », couverture du livre
  en tête et dans une section « Le livre », Équation du But (figure du livre), cercle des 6 plans, icônes Lucide à la place
  des émojis, lien vers l'introduction du livre. Promesse corrigée : le gratuit a « l'essentiel de chaque chapitre », PRO « le
  livre en entier » (aussi dans `abonnement/index`).
- **Couverture** : `assets/images/livre/couverture.png` = la couverture HD **recadrée au-dessus du nom de l'auteur**
  (règle : son nom n'est pas publié tant que les mentions légales ne sont pas prêtes). Quand elles le seront, on pourra
  mettre la couverture complète (`livre-et-ressources/livre/KIINI_couverture_HD.png`).
  Piège : `sips -c` recadre toujours au centre (ignore `--cropOffset`) ; le recadrage a été fait en Python (PNG).
- **Entonnoir** : `Media\Service\FunnelReport`, compteur `daily_visit` (visites de la vitrine par jour, robots écartés, aucune
  donnée personnelle), page `/admin/entonnoir` (visites, comptes, email vérifié, onboarding, parcours, PRO). Les visites ne
  sont comptées que depuis le déploiement. Test : `tests/Media/FunnelTest.php`.
- **Reste (audit n° 2)** : visuels interactifs (cercle des plans cliquable avec progression, carte cliquable, rythmes),
  accueil « Ma prochaine étape », puis lots A, B, C de l'audit n° 1 (zéro émoji dans l'application, composants uniques).
- Pas de capture d'écran de l'application sur la vitrine (pas de photo ni de témoignage non plus : ne rien inventer).

## 2026-11-17 · Identité visuelle du livre dans l'application

- Demande du fondateur : cohérence visuelle avec le livre (couleurs, schémas) ; **les polices restent** (Playfair, Inter).
- Palette du livre dans `base.html.twig` (`:root`) : vert `#2E6B53` / forêt `#1F4D3A`, or `#C8962E`, sauge, neutres
  légèrement verts ; anciennes valeurs turquoise remplacées partout (60 fichiers) ; `teal-*` de Tailwind redéfini.
- Couleurs des plans (`--plan-*`) selon la figure Être/Faire/Avoir/Donner ; appliquées à la vue d'ensemble (5 cartes
  sur une ligne), à la carte stratégique et sa légende, aux pages « visualiser » des plans, à la revue de la semaine.
  Badges PRO en or ; avertissements de l'analyse de cohérence en or.
- Encart « Comprendre cette étape » et citations du livre : vert pâle et filet d'or, icônes au lieu d'émojis.
- Schémas du livre placés (`methode/_figure.html.twig`) : 6 plans (vue d'ensemble), cascade (carte), saisons (Ma saison),
  cycle de l'intendant (Intendance), revues (Bilan), Équation du But, convictions, réseaux, héritage, hygiène, horizons
  (assistant, repliés), racines (rituels), trois témoins (feedback) ; ouvertures de parties en tête des chapitres.
- **Signalé à l'auteur** : la figure Être/Faire/Avoir/Donner dit « AVOIR : De quoi ai-je besoin ? » et BE/DO/HAVE/GIVE
  (non utilisée dans l'application) ; les ouvertures de parties posent les formules en questions (« Qui suis-je… »).
- Rappel : le prénom de l'auteur reste dans l'histoire du chapitre 10 (accord du 17/11) ; la signature reste retirée.

## 2026-11-17 · Phase 2 : le parcours de 90 jours (livre, annexe A)

- `/parcours` (`Methode\Controller\JourneyController`, contenu `Methode\Service\Journey::PLAN`) : les 13 semaines de
  l'annexe A, mot pour mot (chapitres, « ce que tu produis »), avec pour chaque semaine les chapitres à lire et des
  boutons vers les écrans où écrire (étapes de l'assistant, Ma saison, bilan du jour, Plan d'Intendance, rituels…).
- Avancement : `Methode\Entity\JourneyProgress` (date de départ, semaines terminées) ; semaine en cours calculée depuis
  la date de départ (`Journey::currentWeek`). Migration `journey_progress`.
- Entrées : carte en tête de la Méthode (comptes connectés), rappel de la semaine en cours sur l'accueil (`/dashboard`),
  invitation tant que le parcours n'est pas commencé.
- `tests/Methode/JourneyTest.php` vérifie que chaque semaine pointe vers des chapitres et des écrans qui existent :
  à garder vert si l'on renomme un chapitre ou une route. 214 tests verts.
- Les titres des semaines gardent le « tu » du livre (extraits du livre), le reste de la page vouvoie.

## 2026-11-17 · Phase 1 bis : les pratiques du livre dans l'application

- **Vérification visuelle** en local avec un compte de test PRO (`var/compte-de-test.local`, non versionné) : page
  d'Intendance, vue d'ensemble, Équation du But. Corrigé : retour sur l'élément après enregistrement (formulaires sans
  Turbo + défilement vers l'ancre), « Hygiène de vie » dans les titres.
- **Intendance dans la revue du mois** (compte rendu, chapitre 18) : état et action faite des priorités, date du dernier
  point (`MonthlyReviewController::saveStewardship`).
- **Intendance sur la carte stratégique** : lien facultatif d'un élément vers un objectif (`StewardshipItem::lifeGoal`) ;
  nœuds « ⚖️ » sur la carte ; alerte du chapitre 21 « Absent de la carte : … » pour tout plan qui ne soutient aucun objectif.
- **Ma saison** (`/bilan/saison`, `SeasonController`, `Bilan\Service\Seasons`, chapitre 11) : saison de 3 mois, thème,
  nature (semis, croissance, récolte, hiver : travail et piège du livre), revue de saison, historique. Entité `Season`
  existante, jusque-là inutilisée.
- **Bilan du jour** (`/bilan/du-jour`, `DailyReviewController`, chapitre 17) : intention tenue, énergie, humeur, stress,
  gratitude, tendances sur 14 jours. Stocké dans `DailyLog` (déjà utilisé par l'Agenda pour l'intention du jour).
- **Phrase d'éternité** et « déplacement de trésor » (chapitre 19) : étape 11 de l'assistant, affichés en tête du Plan de
  Transmission (`PlanDeTransmission::eternityPhrase`, `treasureShift`).
- **Le Sanctuaire** : chaque encart « Comprendre cette étape » propose l'exercice du chapitre (`BookLibrary::sanctuary`).
- **Ne manque jamais deux fois** (chapitre 22) : rappel sur la page des rituels pour les rituels quotidiens manqués la veille.
- **Axe « Ressources » du test Hygiène** : test inchangé (comparaison des passations) ; à l'étape 12, cet axe n'est plus
  proposé comme domaine d'hygiène mais renvoyé au domaine « Temps » du Plan d'Intendance.
- 3 migrations (lien objectif, saisons et bilan du jour, phrase d'éternité). 212 tests verts.
- **Pièges** : dans un gabarit enfant, ne pas nommer une variable `active` (écrasée par le menu de `base.html.twig`) ;
  `Habit` réécrit `createdAt` au `PrePersist` (dans un test, antidater en SQL puis `clear()`).
- **Choix retenus** : l'application garde le « vous », le livre le « tu » ; le mot reste « Agenda ».

## 2026-11-17 · Le livre : trois niveaux d'accès

Décision du fondateur : le livre est un produit en soi (vente, KDP), l'application fait pratiquer.
- Visiteur : 3 premiers chapitres en entier (`BookLibrary::PUBLIC_CHAPTERS`), extrait des autres.
- Compte gratuit : **l'essentiel** de chaque chapitre (`BookLibrary::essentials` : partie, sous-titre, verset, premier
  paragraphe, Le Sanctuaire, Ce que j'écris, À retenir ; ni les histoires ni « Pour les groupes »), extrait du texte de
  l'auteur sans réécriture.
- PRO (essai compris) : texte intégral. Interrupteur « Le livre en entier (La Méthode) » dans Configuration
  (`AppConfig::enableMethodFree`, désormais faux par défaut ; migration `Version20261117151426` le met à faux).
- Déployé le 17/11/2026 à 15 h 15 (UTC). **Piège du déploiement** : pendant les ~10 s entre l'envoi des fichiers et la
  reconstruction du cache, une nouvelle route (ici `app_plan_d_intendance`, appelée par le menu) donne des erreurs 500
  (6 dans le journal, toutes avant 15:15:46). Sans gravité ; à corriger un jour en vidant le cache avant d'ouvrir le menu.
- Pas de code imprimé dans le livre pour l'instant : KDP imprime le même code dans chaque exemplaire. Idée retenue
  pour la sortie du livre : un code de bienvenue (prolongation de l'essai, une fois par compte), sans risque s'il circule.

## 2026-11-17 · Phase 1 : l'application alignée sur le nouveau livre (6 plans, Plan d'Intendance)

Contexte : l'auteur a ajouté au livre le **Plan d'Intendance : ce que je gère** (manuscrit
`../livre-et-ressources/sources/livre/manuscrit.md`, partie VI, chapitre 18 ; fiche `../livre-et-ressources/CONTEXTE_POUR_IA.md`).
Décision et suite : `docs/DECISION-plan-d-intendance.md`. Le livre et l'application doivent sortir ensemble sans se contredire.

- **6 plans, noms et formules du livre** : `PlanCatalog::PLANS` (clé `INTENDANCE` entre `HYGIENE` et `TRANSMISSION` ;
  la clé `question` porte la formule du livre : « Qui je suis, où je vais », « Ce que je développe », « Ce que je gère »…).
  « Plan de Développement personnel », « Plan d'Hygiène de vie » partout ; plus de « quatre plans » / « 5 plans »
  visibles (navigation, accueil, abonnement, courriels, revue de la semaine). Lexique : `docs/lexique.md`.
- **Module `PlanDIntendance`** (doc `docs/modules/plan_d_intendance.md`) : page guidée (pas d'étape dans l'assistant,
  trop monolithique : `wizard.html.twig` fait 2 000 lignes), inventaire prérempli depuis le Plan de Vie et les tests,
  état, décision, action, 5 priorités au plus, compte rendu ; vue sur une page. PRO, interrupteur gratuit dans
  l'administration (`enableStewardshipFree`). Migration `Version20261117145446`. Carte dans la vue d'ensemble.
- **La Méthode lit le nouveau livre** : `app:livre:sync` découpe le manuscrit en 28 fichiers `content/livre/`
  (`BookLibrary::CHAPTERS`) ; retire dédicace, page auteur, signature et marqueurs « [HISTOIRE PERSONNELLE] »,
  « [À COMPLÉTER] », « [… À CONFIRMER] ». Anciennes adresses redirigées (`BookLibrary::LEGACY_SLUGS`, 301). Encarts
  « Comprendre cette étape » remappés (`STEPS`, dont `intendance` → chapitre 18).
- **Équation du But** dans l'étape 1 de l'assistant (« Je suis créé pour … afin d'apporter … chez … ») : trois champs
  qui écrivent le But ; le champ enregistré reste `purpose_statement`.
- Tests : `tests/PlanDIntendance/`, `MethodeControllerTest::testNewBookWithStewardshipChapter`,
  `WizardTestResultsTest::testPurposeEquationInStepOne` ; 204 tests verts.
- **Pièges** : `docs/livre/` est l'ancienne version (gardée, `README-OBSOLETE.md`) ; sur macOS, `grep -Z` ne sépare
  pas par NUL (utiliser `grep -l | xargs`).
- **Reste (phase 1 bis)** : bilan du jour, saisons (thème de 3 mois, revue de saison), phrase d'éternité dans la
  Transmission, nom « Le Sanctuaire » pour les exercices, « ne manque jamais deux fois », Organisateur ou Agenda (un
  seul mot à choisir) ; Intendance dans la revue du mois et la carte stratégique ; axe « Ressources » du test Hygiène.

## 2026-11-17 · Cohérence des plans, lot 1 : l'assistant reprend les résultats des tests

- `PlanDeVie\Service\TestResultsForPlans` : derniers résultats sous forme utile aux plans (DISC 16 profils, type,
  ennéagramme, Big Five dominant, dons + dons confirmés ou vus par les proches, 3 axes prioritaires du test Hygiène).
  Lit les **scores** (`Assessment::getScores`), pas les rapports illustrés (plus lents) ; noms via `Content::get`.
- Assistant (`WizardController` passe `testResults` et `discOptions`) :
  - étape 5 : préremplie depuis les tests quand rien n'est saisi, mention « Issu de votre test du … » ou « Votre dernier
    test indique … » (partiel `templates/plan_de_vie/wizard/_test_hint.html.twig`), liens « Voir mon profil KIINI SAWA »
    et « Passer les tests » ; « Style comportemental » → **Profil DISC** avec les 16 profils ;
  - étape 4 : puces « + don » (`_gift_suggestions`, JS `addSuggestedTalent`) ; étape 12 : puces des axes prioritaires
    avec la note (`_hygiene_suggestions`, JS `addSuggestedHygieneDomain`) ; étape 11 : rappel « Vos dons » (`_gifts_reminder`) ;
  - étape 2 avec `plan=CROISSANCE` : phrase sur la Balance interne.
- Plan de Transmission ouvert (`strategies.html.twig`) : rappel « Vos dons » (`giftResults`, ajouté par
  `AbstractPlanController::renderStrategies`). Les 4 phrases d'ouverture des plans réécrites selon la grille (doublon « (Être) » retiré).
- **Piège corrigé** : les tests écrivent aussi leurs scores bruts (JSON) dans `ProfileTest` (DISC, BIG5) ; l'assistant
  les prenait pour une saisie et rien n'était sélectionné. `PlanDataProvider::getWizardData` ne garde plus que des
  valeurs de la liste, la plus récente par date.
- Tests : `tests/PlanDeVie/WizardTestResultsTest.php`. Non vérifié à l'œil dans un navigateur (couvert par les tests fonctionnels).
- Reste : lots 2 et 3 de `docs/A-FAIRE-coherence-des-plans.md` (décisions du fondateur).

## 2026-11-17 · Audit de cohérence des 5 plans

- Audit détaillé : **`docs/A-FAIRE-coherence-des-plans.md`** (lot 1 fait ; lots 2 et 3 à décider).

## 2026-11-17 · Les 5 plans : questions, ordre et icônes de la méthode

- Référence fournie par le fondateur (diapositive « Les 5 plans de la méthode KIINI ») : une question par plan, ordre
  Vie → Croissance → Développement → **Hygiène → Transmission**, puis « le Système » (carte, habitudes, revues).
- `PlanCatalog::PLANS` porte désormais `question` et `desc` dans cet ordre (source unique) ; menu, vue d'ensemble
  (cartes réordonnées, question en italique, icônes sprout / wrench / heart-pulse / hand-heart), en-tête de chaque plan
  ouvert et du Plan de Vie. Hygiène : sous-titre « Le soutien » (et non plus « Le Système »).
- « Mon quotidien » présenté comme le Système (description dans le panneau et le menu du téléphone).
- Grille ajoutée à `docs/lexique.md` ; `NavigationTest` vérifie questions, contenus et ordre.

## 2026-11-17 · Correction : contenu du Plan de Croissance

- **Erreur commise** : le menu décrivait la Croissance avec « compétences » (ancienne version à 3 axes du livre).
  Décision du 15/11/2026 (`docs/plan-v1.md` §6.1) : **Compétence & Connaissance → Plan de Développement** ; la Croissance
  garde Caractère & Conviction, Relation & Réseau, valeurs d'alignement.
- **Source unique** des descriptions des plans : `PlanDeVie\Service\PlanCatalog::PLANS[...]['desc']`, utilisée par le menu
  (`NavigationExtension`) et la vue d'ensemble (`strategies.html.twig`). `NavigationTest` le vérifie.
- Corrigé aussi : zone de vocation « L'enseignant » → Plan de Développement ; Équilibre « Développement personnel » →
  Croissance (caractère) et Développement (apprentissages) ; fiche `docs/modules/plan_de_croissance.md`.
- **Attention** : le livre (`docs/livre/CHAP_09_LES_3_AXES.md`, `SUMMARY_PLANS.md`) décrit encore l'ancienne version ;
  ne pas s'en servir pour décrire les plans dans l'interface, et ne pas le réécrire sans l'auteur.

## 2026-11-17 · « Mes stratégies » retiré (décision du fondateur)

- Selon le livre (chapitre 10), une **stratégie** est un moyen d'atteindre un objectif, *dans* un plan : les 4 plans
  ne sont donc pas des « stratégies ». La rubrique « Mes plans » liste désormais directement le Plan de Vie et les
  4 plans (badge PRO pour les comptes gratuits), puis la **« Vue d'ensemble de mes plans »** (ancien hub `/mes-strategies`,
  adresse et route `app_strategies` inchangées).
- Libellés mis à jour (pages, emails, abonnement, bilan) ; `docs/lexique.md` et la charte modifiés ;
  `LexiconTest` interdit « Mes stratégies » et « plans stratégiques » dans le texte visible.
- Le mot « stratégie » reste employé dans son sens du livre (stratégies d'un plan, carte stratégique).

## 2026-11-17 · Navigation par rubriques

- Constat (audit de navigation) : fonctionnalités majeures cachées dans le menu du compte (Mes tests, Agenda,
  Partenaires, Couple…), menu « Plus » du téléphone en liste plate de 15 entrées, ordinateur et téléphone différents.
- Refonte : `Shared/Twig/NavigationExtension` = **une seule définition** ; six rubriques (Aujourd'hui, Me connaître,
  Mes plans, Mon quotidien, Ensemble, La Méthode) avec panneaux sur ordinateur et menu groupé sur téléphone ; menu du
  compte réduit au compte. Adresses des pages inchangées. Détail : `docs/design.md` (Navigation).
- **Piège Alpine** : un `@click.outside` sur chaque panneau (même masqué) referme le menu juste après l'avoir ouvert ;
  un seul `@click.outside` sur la barre.
- Vérifié : le message « Plan de Croissance envoyé… » vu dans le texte de « Mes stratégies » est une notification
  masquée hors écran (pas visible).
- Tests : `tests/Shared/NavigationTest.php`.

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

- **Prochain chantier : décisions du fondateur sur les lots 2 et 3 de `docs/A-FAIRE-coherence-des-plans.md`.**

- Carte DISC d'équipe ou de couple pour les organisations (offre Églises).
- Suivi de la fidélité de tous les tests (Admin › Fidélité des tests couvre l'IPIP) ; normes dès 200 profils.
- Relectures humaines : théologien (figures bibliques), psychologue (textes), juriste (mentions) — côté fondateur.
- Structure juridique (15 « [À COMPLÉTER] »), coordonnées de paiement, site vitrine — côté fondateur.

## 18/11/2026 — Bascule vers kiinisawa.com (code prêt, pas encore déployé)

Décision du fondateur : l'application passe de `app.kiinisawa.com` à `kiinisawa.com` (le site Hostinger Website Builder
est abandonné ; personne n'a encore reçu de lien `app.`, donc pas de redirection). Fait : références remplacées
(README, AGENTS, `env.prod.local.exemple`, page d'installation, rapport de test, brief média, `app:launch:check`,
`docs/mise-en-ligne.md`), test `tests/Shared/CanonicalDomainTest.php` (aucune mention de `app.` dans templates/src/config).
**Reste, côté serveur (à faire par le fondateur puis par moi) :** désactiver le Builder, ajouter `kiinisawa.com` à
l'hébergement vers `~/kiini/public`, SSL ; puis changer `DEFAULT_URI` dans `~/kiini/.env.prod.local`, mettre à jour
`HOSTINGER_DIR` dans `bin/ops/hostinger.local` si le dossier change, vider le cache, vérifier connexion, email, installation.
Connexions, notifications poussées et installation sur téléphone sont liées à l'adresse : tout le monde se reconnecte une fois.

## 18/11/2026 — Vitrine : figure des six plans en SVG

L'image du livre (`fig_6plans.png`) affichait « Plan de Développement » sans « personnel » ; `figs.py` ne se régénère pas sur ce Mac
(`rsvg-convert` et polices Carlito/Caladea absents, sortie vers `/home/claude/...`). La vitrine utilise donc un SVG intégré :
`templates/dashboard/_six_plans_figure.html.twig` (noms complets, couleurs du livre). Le cercle de « Mes plans » dit aussi
« Développement personnel ». **Reste (auteur) :** corriger la ligne 102 de `figs.py` (`"Plan de\nDéveloppement\npersonnel"`) pour le livre.

## 18/11/2026 — Icônes affichées en texte et disparues (suite du lot A)

Deux défauts hérités du remplacement des émojis : (1) des balises `<i data-lucide=…>` rangées dans des tableaux Twig étaient
imprimées échappées (« <i data-lucide… » visible sur Organisation, Couple, humeurs du journal et de l'accueil, états de la
carte du Plan de Vie) → `|raw` ajouté ; (2) Turbo remplaçait le `<body>` sans redessiner les icônes (menu sans pictogrammes
après un clic) → `turbo:load/render/frame-load` appellent `lucide.createIcons()`. Étiquettes des diagrammes Mermaid : plus de balise
d'icône (elle y était invisible). Gardes : `NavigationTest::testNoIconMarkupIsDisplayedAsText`, `DesignRulesTest::testIconsAreRecreatedAfterTurboNavigation`.
Leçon : après tout remplacement en masse, rendre les pages et chercher `&lt;i data-lucide` dans le HTML.

## 18/11/2026 — Rapports de tests : PDF plus propre (DISC mesuré)

Constat sur le PDF DISC du fondateur (16 pages) : 3 pages presque vides (5, 7, 12 : quelques lignes débordées), page de garde bleu
nuit hors palette du livre, pastille « Modérée » mal alignée, question réflexe coupée (« ? » seul en bout de ligne).
Corrigé (rendu local mesuré : 13 pages, aucune page orpheline) : compaction du PDF (interligne, marges, tuiles) dans
`report/_styles.html.twig`, boussole plus petite, hauteur minimale des tuiles « vu par les autres », espaces insécables avant « ? »,
intensité en étiquette lisible, page de garde en vert du livre (`report/_cover.html.twig`, commune à tous les rapports).
Outil de mesure : générer le PDF via un test temporaire (`tests/Personnalite/DiscTest.php` montre comment créer un `Assessment`), puis
le rendre avec PDFKit (swift) ; poppler/ImageMagick absents sur le Mac. **Pas encore revu :** écran (page web du rapport), Big Five,
ennéagramme, dons, synthèse : mêmes styles communs, à contrôler de la même façon.

## 18/11/2026 — DISC : « Regard croisé » avec le Big Five

Le test « Style comportemental » n'est plus proposé depuis l'arrivée du DISC (absent de `Instruments::GROUPS`, voir `DiscTest`) ; il ne
sert qu'à relire d'anciens rapports. Son calcul (`Scorer::style`) existe déjà dans tout résultat Big Five / Profil complet.
Ajout : `ReportBuilder::crossView()` confronte le DISC au style déduit du Big Five le plus récent (confirmé / voisin / nuancé) sans
mélanger les scores ; bloc dans `disc/_report.html.twig` (page « Votre profil en situation », écran et PDF) ; sans Big Five, invitation
à le passer (écran seulement). Branché dans `AssessmentController::report` et `reportPdf`. Tests : `tests/Personnalite/CrossViewTest.php`.
Pagination PDF mesurée : toujours 13 pages, aucune page orpheline.

## 18/11/2026 — « Mon profil KIINI SAWA » : lectures croisées (InsightEngine)

Demande du fondateur : une vraie intelligence derrière la synthèse, et pas seulement un agrégat. Ajouté : `Service/InsightEngine.php`
(13 règles explicites, preuves citées, confiance selon le nombre de tests, question + premier pas relié à un plan), page « Lectures croisées »
dans la synthèse, encart « Ce que vos autres tests ajoutent » dans chaque rapport (écran et PDF, PRO ; teaser pour le gratuit),
partial `report/_insight_card.html.twig`. Pagination PDF mesurée (synthèse 10 p., ennéagramme 12 p., DISC 14 p., aucune page orpheline).
**Honnêteté :** règles et seuils écrits à la main, non validés statistiquement ; `ENNEAGRAM_EXPECTS` est une lecture usuelle, pas une
preuve. **Reste :** recalibrer avec de vrais résultats ; lire aussi le regard des proches (ConfirmationService) dans le moteur ; lectures
internes à chaque test (facettes Big Five qui se contredisent, combinaisons de dons) ; moteur de vocation qui pèse aussi ennéagramme et Big Five.

## 18/11/2026 — Lectures internes et vocation nuancée

Suite de l'InsightEngine : 7 rapprochements de facettes du Big Five et 7 paires de dons (lectures « internes », drapeau `internal`),
`insightsFor()` renvoie jusqu'à 2 lectures croisées + 1 interne par rapport ; panneau titré « Une lecture au sein de ce test » quand il n'y a
que de l'interne. Zone de vocation : bloc « Comment l'exercer, selon votre tempérament » (`vocationNuances`, Big Five). Tests :
`InsightEngineTest` (10). Pagination PDF contrôlée. **Non vu à l'écran :** aucun profil de test local ne déclenche encore ces règles internes ;
elles sont couvertes par des tests unitaires.

## 18/11/2026 — Le regard des proches entre dans le moteur

`InsightEngine` reçoit le regard des proches (DISC, dons) via `ProfileSynthesis::closeOnes()` ; 6 lectures nouvelles + renfort de la lecture
« adaptation excessive » ; invitation à demander ce regard dans la synthèse (`closeInvites`). Anonymat : seules les synthèses ≥ 3 réponses.
Tests : `InsightEngineTest` (15, dont un parcours réel avec trois réponses de proches). **Reste :** recalibrage avec de vrais résultats ;
ajouter d'autres tests au regard des proches (Big Five, ennéagramme) si le fondateur le décide.

## 18/11/2026 — Fiches de personnages bibliques (pilote)

À la demande du fondateur (les deux phrases par figure « n'apportaient rien ») : 3 fiches complètes (Élie, Joseph, Timothée), structure
« Dans le texte / Notre lecture / Fragilité / Grâce / Le Christ au centre », citations LSG vérifiées par `bin/outils/bible.py` et
`BiblicalFiguresTest`. Texte biblique téléchargé avec l'accord du fondateur dans `var/bible/` (non versionné). Intégrées au rapport DISC (profils D
et SC) ; les mentions courtes « même famille » sont masquées quand des fiches existent. Pagination PDF contrôlée (aucune page orpheline).
**En attente :** relecture doctrinale par le fondateur (`reviewed => false`), puis extension.

## 18/11/2026 — Fiches bibliques : le profil d'abord

Retour du fondateur : pour qu'un rapprochement soit crédible, il faut montrer d'abord les traits du profil chez le personnage, ensuite
commenter. Refonte de `_figure_sheet.html.twig` : en tête « Son profil : SC » avec, pour chaque dimension DISC, la définition de l'application
(essence), la solidité des indices (nombreux / réels / indirects) et un tableau trait · ce que dit le texte · référence ; puis « Ce que le texte ne
montre pas » (contre-indices) et les limites ; ensuite seulement le lien avec le lecteur, le récit (épisodes étiquetés par dimension), la
fragilité, la grâce et le Christ au centre. Le bloc « Notre lecture » disparaît (remplacé par le profil). Données : `BiblicalFigures[...]['profile']`.
Test : chaque dimension a au moins deux indices référencés (verset cité).

## 18/11/2026 — Bibliothèque de personnages bibliques (32 fiches)

« Lance le tout » : 29 fiches ajoutées (JSON dans `content/personnages/`) sur le modèle validé (profil et indices d'abord, puis récit, fragilité,
grâce, Christ au centre ; cadre doctrinal du fondateur ; LSG). Citations vérifiées par le test (texte biblique local, non versionné). Cas
honnêtes signalés dans les fiches : indices indirects (Timothée, Béréens, Luc), fiches minces (Lydie, Tabitha, Betsaleel), contre-indices (Moïse « fort
patient » ; Jean « fils du tonnerre »), livre d'Esther qui ne nomme pas Dieu. **Non relues par le fondateur** (`reviewed: false`). Reste : relecture doctrinale,
extension aux autres tests (ennéagramme, dons, type) avec la même bibliothèque.

## 18/11/2026 — Fiches de personnages modifiables dans l'administration

Demande du fondateur. Les fiches deviennent une source de `Content` (`figures.library`) : édition par la page « Textes des tests » existante, sans nouveau code d'écran.
Champ `relecture` (« en cours » / « faite ») remplace `reviewed`. Citations LSG et noms de rattachement verrouillés. Libellés lisibles pour les indices du profil.
Piège : `Content::defaults()` est appelé souvent ; `BiblicalFigures::raw()` met les fichiers JSON en cache.

## 18/11/2026 — Accueil « Ma prochaine étape » et cercle des plans sur téléphone

**Accueil :** `Dashboard/Service/NextStep` (règles ordonnées : fondations, bilan du jour à partir de 18 h, revue de la semaine samedi soir et dimanche, parcours de
90 jours, plan accessible le moins avancé, sinon relire le Plan de Vie) renvoie UNE action + deux « ensuite » ; carte en tête de l'accueil (au-dessus du bandeau sur
téléphone), les anciens encarts du parcours disparaissent (l'action est dans la carte). Tests : `tests/Dashboard/NextStepTest.php` (heure injectable), `JourneyTest` adapté.
**Cercle des plans :** vérifié en vue téléphone (375 px) : libellés trop petits ; disques 62 → 72, police 14 → 16 px (15 px sous 420 px), cadrage du `viewBox` resserré,
« LE SYSTÈME » masqué sous 560 px. **Reste :** libellés sous les icônes de la barre du bas sur téléphone ; page « Mes rythmes ».

## 18/11/2026 — Six fiches fragiles remplacées

Demande du fondateur : remplacer les personnages à matière mince par des personnages « évidents ». Remplacements (même code DISC) : Béréens → Nicodème (C), Lydie → Jonathan (IS),
Tabitha → Élisabeth (SI), Jean le disciple bien-aimé → Marie de Béthanie (SI), Luc → Aquilas et Priscille (CI), Betsaleel → Boaz (CS). `DiscCombos` mis à jour (nom, référence,
phrase courte), fichiers JSON nettoyés, nouveau `08-…json`. Pagination PDF contrôlée (profils C, CI, CS, IS, SI). Citations vérifiées par le test.
