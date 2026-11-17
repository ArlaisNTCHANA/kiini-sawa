# Fiche de décision : le Plan d'Intendance dans l'application

Rédigée le 17/11/2026. **Phase 1 livrée le même jour** (voir `docs/SUIVI-IA.md`) avec les propositions par défaut :
formules du livre, PRO avec interrupteur gratuit, pas de partage Duo, nouveau livre dans la Méthode. Choix
d'architecture : une page guidée plutôt qu'une étape 14 de l'assistant. Restent : la décision 2 (axe « Ressources »
du test Hygiène) et les liens avec la revue du mois et la carte stratégique. Elle répond au point A du lot 2 de
`docs/A-FAIRE-coherence-des-plans.md`, où l'« Avoir » n'avait pas de plan.

**Source de vérité : le livre.** Elle se compose de :
- le manuscrit `livre-et-ressources/sources/livre/manuscrit.md`, partie VI, chapitre 18 « Gérer ce qui m'est confié » ;
- la fiche `livre-et-ressources/CONTEXTE_POUR_IA.md` ;
- les schémas `illustrations/fig_6plans.png` et `fig_intendance.png`.

L'application suit le livre. Elle ne réinvente ni le vocabulaire ni le contenu.

## 1. Ce que l'auteur a décidé (17/11/2026)

| Sujet | Décision |
|---|---|
| Nom | **Plan d'Intendance**, formule « **Ce que je gère** » |
| Texte central | Matthieu 25 (les talents) ; Luc 16:10 ; 1 Corinthiens 4:2 ; 1 Pierre 4:10 |
| Ordre des 6 plans | Vie → Croissance → Développement personnel → Hygiène de vie → **Intendance** → Transmission, puis le Système qui les relie |
| Être, Faire, Avoir, Donner | Être → Croissance, Faire → Développement, **Avoir → Intendance** (au sens de « ce qui m'est confié »), Donner → Transmission |
| Contenu | **Tout ce qui est déjà entre mes mains et dont je rendrai compte**, et pas seulement l'argent |
| Les 7 domaines | Finances et biens ; Temps ; Foyer et enfants ; Ressources intellectuelles ; Influence et position ; Responsabilités confiées ; Opportunités et accès |
| Reprise du Plan de Vie | But, dons, passions, personnalité, vision et saison, chacun avec la question « qu'est-ce que j'en fais ? » |
| Cycle | 1. Inventaire (ce qui m'est confié) → 2. État (**fructifie, dort, fuit**) → 3. Décision (**affecter, protéger, libérer**) + action des 30 prochains jours → 4. Compte rendu (je fais le point) |
| Frontières | Un don est identifié (Vie), aiguisé (Développement), géré (Intendance), transmis (Transmission). Les enfants : l'Intendance demande comment j'en prends soin aujourd'hui, la Transmission ce que je leur laisse. Le temps : l'Intendance décide de sa répartition, l'Agenda l'exécute. Le corps reste dans l'Hygiène, le réseau dans la Croissance. |
| Garde-fous | L'intendance n'est ni une méthode pour s'enrichir (« si ton plan ne parle que d'argent, il a perdu son centre ») ni un fardeau anxieux |

Ce que le plan contient, d'après « Ce que j'écris dans mon Plan d'Intendance » et l'annexe B :
- mon inventaire ;
- l'état de chaque élément ;
- mes décisions, chacune avec une action mesurable ;
- mon rythme de compte rendu, en précisant quand et avec qui.

## 2. Ce que l'application ferait (proposition)

### Un module et un plan
- Un nouveau module `src/PlanDIntendance/`, créé avec `app:module:create`, ses gabarits dans `templates/plan_d_intendance/` et sa documentation dans `docs/modules/plan_d_intendance.md`.
- `PlanCatalog::PLANS` reçoit une clé `INTENDANCE`, placée entre `HYGIENE` et `TRANSMISSION`, avec la route `/plan-d-intendance`. La navigation, la vue d'ensemble des plans et la carte stratégique suivent automatiquement, puisque `PlanCatalog` est leur source unique.

### Les données
Deux entités nouvelles :

**`StewardshipItem`, « ce qui m'est confié »**

| Champ | Contenu |
|---|---|
| utilisateur | |
| `source` | un des 7 domaines, ou une sortie du Plan de Vie (`VIE_BUT`, `VIE_DON`, `VIE_PASSION`, `VIE_PERSONNALITE`, `VIE_VISION`, `VIE_SAISON`) |
| libellé | |
| état | `FRUCTIFIE`, `DORT`, `FUIT`, ou vide tant qu'il n'est pas évalué |
| décision | `AFFECTER`, `PROTEGER`, `LIBERER` |
| action | l'action des 30 prochains jours |
| prioritaire | oui ou non : 3 à 5 éléments prioritaires, comme dans le livre |
| objectif de vie | lien facultatif |
| rituel | lien facultatif, sur le modèle de `Habit::skillGoal` |
| ordre, dates | |

**`StewardshipRhythm`, le rythme de compte rendu**
- la fréquence : chaque semaine, chaque mois ou chaque saison ;
- un partenaire de redevabilité, facultatif, choisi parmi les Partenaires.

Le compte rendu lui-même se fait dans les revues existantes (module Bilan), sans nouvelle entité. Voir plus bas.

### L'assistant
- Il gagne une **étape 14 « Plan d'Intendance »**, avec `STEPS_BY_PLAN['INTENDANCE'] = [14]`. Elle se déroule en trois temps : l'inventaire, puis l'état, puis les décisions.
- **L'inventaire est prérempli à partir de ce qui existe déjà**, sur le même principe que le lot 1 avec `TestResultsForPlans` : le But, les dons (ceux du test, ceux saisis et ceux confirmés par les proches), les passions, la vision et la saison du Plan de Vie. Ils apparaissent en puces « + Ajouter ». Rien n'est à ressaisir.
- Les 7 domaines s'affichent chacun avec sa question d'intendant, tirée du tableau du livre. L'utilisateur y note ce qui lui est confié.
- Le choix de l'état et de la décision se fait en un clic : trois boutons pour l'un, trois pour l'autre.

### Le plan ouvert
Il garde les onglets habituels :
- **Intention** : le verset et la phrase « Rien de ce que tu as ne t'appartient vraiment. Tout t'est confié. »
- **Axes** : les 7 domaines, plus « Ce que mon Plan de Vie a mis entre mes mains ».
- **Lois** : fidèle dans le peu ; le maître ne compare pas les montants ; la fidélité ouvre sur davantage.
- **Système** : les rituels liés et le rythme de compte rendu.
- **Échéancier** : les actions des 30 prochains jours.

Un tableau reprend l'exemple du livre, avec les colonnes « Ce qui m'est confié », « État », « Décision » et « Action ».

### Les liens avec le reste
- **Revue du mois** (Bilan) : une question « Mes décisions d'intendance » passe en revue les éléments prioritaires, avec leur action faite ou non et leur nouvel état. C'est l'étape 4 du cycle.
- **Agenda** : une action d'intendance peut devenir une tâche.
- **Carte stratégique** : l'Intendance devient un « plan de soutien » possible pour chaque objectif. L'alerte « un de mes six plans est absent de la carte » l'inclut.
- **Plan de Transmission** : le rappel « Vos dons » reste. L'Intendance a son propre rappel des dons qui dorment.

### La confidentialité de l'argent
- Les montants sont **facultatifs**. Le plan fonctionne avec des mots seuls, par exemple : « Mon épargne : elle dort, protéger, mettre de côté une part fixe ».
- Aucun lien bancaire.
- Le partenaire de redevabilité ne voit que ce que l'utilisateur choisit de partager, comme pour les autres plans.

## 3. Décisions encore à prendre par le fondateur

1. **Les formules des plans dans l'application.** Le livre utilise des formules du type « ce que je gère ». L'application utilise des questions, par exemple « Que vais-je laisser ? ». La formule du Développement a changé dans le livre : « ce que je développe » au lieu de « ce que je maîtrise ».
   - Proposition : **adopter les formules du livre partout**. Qui je suis, où je vais / Qui je deviens / Ce que je développe / Ce qui me soutient / Ce que je gère / Ce que je laisse.
   - Et les noms complets : « Plan de Développement personnel », « Plan d'Hygiène de vie ».
2. **L'axe « Ressources » du test Hygiène.** Il regroupe aujourd'hui « Gestion du temps », « Projets personnels », « Limites d'énergie » et « Récupération » (`HygieneDomain.php`, l. 240).
   - Proposition : la **gestion du temps** et les **projets** passent à l'Intendance ; l'**énergie** et la **récupération** restent dans l'Hygiène, au sein d'un axe « Corps » ou « Repos ».
   - À traiter avec le point D du lot 2. Cela change les questions du test et la comparaison avec les anciennes passations.
3. **PRO ou gratuit ?** Aujourd'hui, les quatre plans autres que le Plan de Vie sont-ils tous PRO ? L'Intendance suit la même règle, sauf avis contraire.
4. **Espace couple (Duo).** Le domaine « Foyer et enfants » peut-il être partagé avec le conjoint ? Proposition : pas dans la première version.
5. **Le livre dans l'application (Méthode).** `app:livre:sync` lit `web/docs/livre`, une ancienne version courte en 15 chapitres, sans l'Intendance. Faut-il la remplacer par le nouveau manuscrit découpé en chapitres ? Il faudra aussi choisir les chapitres publics.

## 4. Travaux et estimation (après accord)

| Lot | Contenu | Durée |
|---|---|---|
| Libellés | `PlanCatalog`, lexique (`docs/lexique.md`, « 6 plans »), `LexiconTest`, textes qui disent « 5 plans » ou « quatre plans » (navigation, accueil, abonnement, courriels d'accueil, revue de la semaine) | ½ jour |
| Module | Entités, migration (tester sur MariaDB), plan ouvert, rythme de compte rendu | 1 jour |
| Assistant | Étape 14, inventaire prérempli depuis le Plan de Vie et les tests | 1 jour |
| Liens | Revue du mois, carte stratégique, agenda | ½ jour |
| Test Hygiène | Déplacement de l'axe « Ressources » (selon la décision 2) | ½ jour |
| Méthode | Nouveau livre dans l'application (selon la décision 5) | ½ jour |

Total : **environ 4 jours**, avec tests, journal `docs/SUIVI-IA.md`, déploiement et vérification de la production.

Les lots 2 B (Confirmations), 2 C (Mentorat & Service) et 3 (entités du Développement) restent indépendants et peuvent se faire avant ou après.
