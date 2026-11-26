# À faire : corrections issues du parcours « nouvel utilisateur » (25-26/11/2026)

Feuille de route pour l'IA ou le développeur qui prend la suite. **À lire d'abord** : `AGENTS.md`, puis le rapport
complet `docs/parcours-nouvel-utilisateur-2026-11-25.md` (carnet étape par étape, top 10, captures dans
`docs/parcours-nouvel-utilisateur-2026-11-25/`).

## Règles de travail (rappel)

- Le fondateur n'est pas développeur : expliquer en français simple, sans jargon. Il valide chaque chantier.
- Respecter `docs/charte-projet.md`, `docs/lexique.md`, `docs/design.md`. Interface **100 % en français**, sans code brut.
- **Toujours « Partenaire de Redevabilité » en entier**, jamais « partenaire » seul (règle du fondateur).
- Chaque correction : un test qui échoue avant et passe après, puis `php bin/phpunit` entier (604 tests verts au 26/11).
- Après chaque chantier : compléter `docs/SUIVI-IA.md` (le plus récent en haut), commit en français avec la
  ligne `Co-Authored-By`. Déploiement : `bin/ops/envoyer-hostinger.sh`, **après accord du fondateur**. Ensuite, vérifier
  l'accueil (200) et le journal `var/log/prod-AAAA-MM-JJ.log`. **Le fondateur fait lui-même `git push`.**
- Ne jamais demander, afficher ni saisir de mot de passe ou de code. Ne pas publier le nom ni la photo du fondateur.
- Compte de test en production : prénom « Testeur » (adresse connue du fondateur, à ne pas écrire dans les documents).
  Pour tester à l'écran, demander au fondateur de se connecter lui-même dans le navigateur.

## Déjà fait (ne pas refaire)

| Quoi | Où | Commit |
|---|---|---|
| Plan de Transmission en erreur 500 au premier enregistrement (doublon du plan) | `src/PlanDeVie/Service/LifePlanWizardSaver.php` (helpers `planDeCroissance/Transmission/DHygiene`), test `tests/PlanDeVie/WizardFirstSaveTest.php` | a7a49aa |
| Équilibre de Vie et Hygiène de Vie notés 0/10 (champ caché répété) | `templates/personnalite/test.html.twig`, `src/Personnalite/Controller/PersonalityController.php::submit`, test `tests/Personnalite/ScaleAnswersTest.php` | a7a49aa |
| Déploiement de ces deux correctifs | serveur | 26/11/2026 |

**Reste à faire sur ces deux points** :
- Vérifier **à l'écran** sur kiinisawa.com, compte « Testeur » (le fondateur doit se reconnecter) : enregistrer le Plan
  de Transmission, puis repasser l'Équilibre de Vie. Ce compte garde un ancien rapport à 0/10 et doit repasser le test.
- Décider avec le fondateur du sort des passations Équilibre et Hygiène déjà enregistrées : elles sont fausses, car toutes
  les réponses valaient 0. Option : les compter (`profile_test`, types `EQUILIBRE`/`HYGIENE`), puis les signaler
  ou proposer de repasser le test. Ne rien supprimer sans son accord.
- Erreur JavaScript vue en production quand on passe d'un assistant à l'autre sans recharger la page (Turbo) :
  « Identifier 'allLifeGoals' has already been declared ». Elle vient de `const allLifeGoals = [...]` dans un `<script>`
  en ligne (`templates/plan_de_vie/wizard.html.twig`, vers la ligne 1777). Remède : placer le script dans une portée
  propre (IIFE, ou `window.allLifeGoals = …`), ou désactiver Turbo sur ces liens (`data-turbo="false"`).
  À vérifier : l'enregistrement automatique et les boutons « Ajouter » marchent encore après une navigation Turbo.

## À faire, par ordre d'importance

### 1. Assistants des plans sur téléphone (gênant fort)
**Symptôme** : sur un écran de 375 px, la carte de l'assistant est une petite fenêtre d'environ 200 px de contenu utile.
Elle défile à l'intérieur de l'écran, il y a 2 à 3 défilements imbriqués (page, carte, liste des objectifs) et le
contenu glisse vers la gauche après « Ajouter » (libellés coupés). Captures 16 et 23. Chaque étape s'ouvre déjà
défilée au milieu.
**Où** : `templates/plan_de_vie/wizard.html.twig` (styles en tête de fichier, `.wizard-body` vers la ligne 18, et les
blocs ajoutés par JavaScript vers les lignes 1840-1900) ; `assets/controllers/wizard_controller.js`.
**Piste** : sur mobile, laisser la page défiler normalement (pas de hauteur fixe ni de `overflow:auto` sur la carte) ;
`overflow-x: hidden` ou `min-width: 0` sur les conteneurs flex et grid qui débordent ; remettre le défilement de la
carte en haut à chaque changement d'étape ; supprimer le `max-height` + `overflow-y:auto` de la liste des objectifs
(`checkbox-list-container`) sur mobile.
**Test** : vérifier à l'écran en 375 × 812 (navigateur intégré, préréglage « mobile ») sur les 5 assistants, puis
ajouter un test de gabarit si possible.

### 2. Promesses du gratuit (gênant fort)
**Symptôme** : le résultat de la Boussole dit « bâtissez vos 6 plans dans votre espace gratuit » et « votre compte
vous ouvre vos 5 tests approfondis ». L'offre gratuite donne en réalité le Plan de Vie, plus DISC et Dons spirituels
(tableau de `/abonnement`, `App\Abonnement\Service\Entitlements`).
**Où** : `templates/shared/boussole.html.twig`.
**À faire** : reformuler avec le fondateur (proposer un texte, ne pas l'inventer seul). Par exemple : « posez votre Plan
de Vie gratuitement, et essayez les 6 plans pendant 14 jours avec PRO ». Passer par `t('code', 'texte')` si la
zone est déjà modifiable (voir `docs/modules/contenu.md`).

### 3. Fin du Plan de Vie : féliciter et montrer le score (gênant)
**Symptôme** : « Sauvegarder mon Plan » ramène sur Aujourd'hui avec un simple bandeau. Le score de cohérence (71, puis
94 après les autres plans) reste caché derrière le bouton « Analyse ». Dans l'accueil, le bouton « Voir mon Plan de
Vie » mène à Aujourd'hui.
**Où** : `src/PlanDeVie/Controller/WizardController.php` (redirection après l'enregistrement),
`templates/compte/onboarding.html.twig` et `templates/dashboard/index.html.twig` (bouton « Voir mon Plan de Vie »).
**Piste** : après la dernière étape, rediriger vers la page du plan avec l'analyse ouverte et un message de
félicitations (le score, les 2 ou 3 pistes principales). Faire pointer le bouton de l'accueil vers `/plan-de-vie`.

### 4. Une seule prochaine étape sur Aujourd'hui (gênant)
**Symptôme** : « Ma prochaine étape : bilan du jour », « Vos premiers pas : 2. Écrire votre vision » et « Ensuite :
commencer mon parcours » se font concurrence. Une fois les 6 plans écrits, on propose encore « le parcours de 90
jours pour écrire vos six plans ».
**Où** : `src/Dashboard/Service/NextStep.php`, `templates/dashboard/index.html.twig`.
**Piste** : tant que « Vos premiers pas » n'est pas terminé, la prochaine étape doit être le premier pas non fait.
Ne proposer le parcours de 90 jours que si les plans ne sont pas encore écrits.

### 5. Retirer l'anglais et le jargon (gênant)
| Texte vu | Où | Remplacer par (à valider) |
|---|---|---|
| `[GIVE] [DO] [BE] [HAVE]` sur les blocs ajoutés | `wizard.html.twig`, JS `getLifeGoalsCheckboxesHtml` (`lg.category.toUpperCase()`, vers la ligne 1789) | même libellé que le filtre `categorie_objectif` (Donner/Faire/Être/Avoir) : envoyer le libellé dans `allLifeGoals` |
| `Ex: ...` sur les blocs ajoutés | `wizard.html.twig` vers la ligne 1861 | un vrai exemple par rubrique |
| « (BE, DO, HAVE, GIVE) », « objectifs de type GIVE » | `wizard.html.twig` (vers la ligne 1256), `plan_de_vie/index.html.twig` | « Être, Faire, Avoir, Donner », « objectifs Donner » |
| `DAILY / WEEKLY / MONTHLY` sur les cartes de rituels | `templates/rituels/index.html.twig` vers la ligne 571 (`habit.frequency.value`) et le formulaire `src/Rituels/Form/HabitType.php` (EnumType sans libellés) | Quotidien / Hebdomadaire / Mensuel (`choice_label`) |
| `[CHARACTER] [RELATION] [COMPETENCE]` | `templates/rituels/index.html.twig` vers la ligne 773 (`gg.axis`) | Caractère / Relation / Compétence |
| « onglet Habit-Rituels » | `templates/planner/index.html.twig` | « page Rituels » |
| « masterminds », « Binômes d'imputabilité », « catalyser votre réussite », « mentor expert en levée de fonds », « pitcher », « Finance d'entreprise », « consultant indépendant » (exemple de vision) | `templates/plan_de_vie/wizard.html.twig` | vocabulaire du livre, exemples chrétiens et concrets ; « Binômes d'imputabilité » → « Partenaire de Redevabilité » |
| « Audit d'hygiène non détecté » | `wizard.html.twig` | « Vous n'avez pas encore fait l'audit Hygiène de vie » |
| Phrases qui commencent par une minuscule (« les recommandations santé… », « ton énergie… ») | encart Corps / âme / esprit de l'étape Hygiène (`wizard.html.twig` ou contenu du livre) | majuscule |

Ajouter les termes interdits à `tests/Shared/LexiconTest.php` pour qu'ils ne reviennent pas.

### 6. Partenaire de Redevabilité : un seul vocabulaire, deux espaces clarifiés (gênant)
**Symptôme** : « Mes partenaires » (réseau d'utilisateurs inscrits) et « Ma redevabilité » (personne extérieure) se
confondent. Les noms varient : « partenaire de confiance », « Partenaires », « partenaire de redevabilité ». « Kiini
Sawa » avec une casse différente.
**Où** : `templates/social/index.html.twig`, `templates/social/accountability/`, `src/Shared/Twig/NavigationExtension.php`
(entrées du menu « Partenaires », « Ma redevabilité »), `docs/lexique.md` (le lexique dit « Partenaires » : à
réconcilier avec la règle du fondateur, **lui demander**).
**Piste** : un court texte en tête de page qui explique la différence en une phrase ; « Partenaire de Redevabilité »
écrit en entier dans les titres ; « KIINI SAWA » en capitales.

### 7. Relier ce que l'utilisateur fait à ses plans (détail utile)
- Un rituel créé est « GÉNÉRAL » : proposer dès la création de le relier à un objectif ou à un plan, ou au moins
  indiquer l'onglet « Enracinement » (`templates/rituels/index.html.twig`). Les domaines d'Hygiène sont absents de
  la liste des liens possibles.
- Le rapport Dons spirituels n'a pas de bouton « Ajouter à mon Plan de Vie (dons et talents) »
  (`templates/personnalite/assessment/report/…`).
- Les priorités d'Intendance ne comptent ni dans le score de cohérence (une piste dit « épargne non soutenue » alors
  qu'une priorité d'Intendance y est reliée) ni dans la revue de la semaine (`src/Bilan/…`, service d'analyse de
  cohérence du Plan de Vie). Le Plan de Transmission est aussi absent de la revue.
- La Boussole 360 n'apparaît pas dans « Mes tests » (0/9) ; les compteurs diffèrent (« 0/9 » contre « 1 test sur 5 »
  dans Mon profil).

### 8. Chapitres du livre depuis l'assistant (détail)
- À la fin d'un chapitre ouvert depuis l'assistant, ajouter « Revenir à mon plan / écrire maintenant » (passer
  l'adresse de retour dans le lien « Lire le chapitre »). `templates/methode/…`, `src/Methode/…`.
- Sur la page Plan de Vie, l'encart affiche « Comment lire ce livre » : `src/Methode/Service/BookLibrary.php` ligne 81,
  `'plan_de_vie' => 'comment-lire'` (appelé par `book_panel('plan_de_vie')` dans `templates/plan_de_vie/index.html.twig`).
  Pointer vers le chapitre du But de Dieu (`but-de-dieu`) ou un chapitre d'introduction au Plan de Vie, avec l'accord du fondateur.

### 9. Détails visuels et textes (liste à traiter en lot)
- Vitrine : devise par défaut FCFA même en Europe ; offre Duo placée avant le sélecteur de devise.
- Boussole : le bouton touché reste surligné à la question suivante ; « 100 % » affiché avant la dernière réponse ;
  « Voir mon résultat » sous la ligne de flottaison.
- Inscription et accueil : « email » ou « e-mail » (garder « e-mail ») ; prénom redemandé ; bandeaux verts qui restent ;
  pastille de valeur non surlignée ; exemple tronqué à l'étape 4.
- Aujourd'hui : « 0/0 rituels du jour » ; raison d'être affichée deux fois ; blocs qui apparaissent avec retard
  (zone blanche).
- Agenda : fenêtre « Nouvelle tâche » collée aux bords et bouton coupé ; aucune confirmation quand la tâche est
  prévue un autre jour ; vue Jour coupée à gauche.
- Journal : ajouter un type « Prière » ; un seul type surligné à l'ouverture ; afficher l'humeur sur la note.
- Tests : boutons de réponse trop petits sur téléphone (Dons spirituels) ; bouton final caché sous la barre du bas ;
  Équilibre de Vie dans un style à part (bleu) et question 5 sans Dieu (« connexion à quelque chose de plus grand »).
- Intendance : tableau des priorités coupé sur téléphone ; rechargement de la page à chaque ajout.
- Abonnement : bouton « Payer » affiché alors que le paiement n'est pas ouvert ; « Écrivez-nous » sans adresse.
- Académie : « Votre réponse peut aller dans : Journal : … » (phrase de machine) ; pas de « Bravo » après une bonne
  réponse.
- Accessibilité : liens de la barre du bas sans nom ; boutons de réponse des tests non annoncés comme boutons.

## Non testé pendant le parcours

Paiement, invitations à de vraies personnes (Feedback nourricier, Partenaire de Redevabilité, conjoint, Sanctuaire du
Couple), tests DISC, Profil complet, Type, Ennéagramme et audit Hygiène, téléchargements PDF.
