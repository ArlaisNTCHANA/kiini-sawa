# Passation : l'application mobile native KIINI SAWA (Expo)

Document écrit le 18/11/2026 pour l'IA (ou le développeur) qui prend en charge la version mobile native. **À lire en entier, puis lire `AGENTS.md`, `docs/charte-projet.md`, `docs/lexique.md` et `docs/design.md`.** Le fondateur parle français : toute l'interface et toute la communication avec lui sont en français.

---

## 1. Mission

Construire **l'application iOS et Android** de KIINI SAWA avec **Expo / React Native**, branchée sur le site Symfony déjà en production (**https://kiinisawa.com**). Périmètre voulu par le fondateur : « tout », livré par étapes (voir §8). Priorité : l'usage quotidien (rituels, bilan du soir, journal, rappels), puis les plans, puis les tests.

KIINI SAWA est une application de planification de vie **chrétienne assumée**, en français, pour l'Europe et l'Afrique francophone. La méthode vient d'un livre (6 plans : Vie, Croissance, Développement personnel, Hygiène de vie, Intendance, Transmission). Slogan : « Donnez du poids à ce qui compte vraiment ». Contact public : contact@kiinisawa.com.

## 2. État des lieux

| Élément | État |
|---|---|
| Site web (`web/`, Symfony 8.1, PHP 8.5, MariaDB) | **En production**, https://kiinisawa.com, déployé par le fondateur sur demande ; dépôt GitHub `ArlaisNTCHANA/kiini-sawa` (privé) |
| Application installable (PWA) | En ligne. Nouveau : **mode rapide** `/rapide` (rituels, bilan du jour, journal, hors connexion, synchro sans doublon). Voir `docs/modules/rapide.md` : **c'est le modèle à reprendre** |
| `mobile/` (ancienne app Expo 54) | **Gelée, ne pas s'en servir comme base** : branchée sur l'ancien site Next.js (`web-v0-nextjs`), SQLite/Drizzle/Supabase, 7+ fichiers non commités, identifiants `com.anonymous...`. Dépôt : `ArlaisNTCHANA/kiini-mobile`. La consulter pour s'inspirer d'écrans, pas pour la réutiliser |
| API pour le mobile | **Partielle** : seul `/api/rapide/*` est pensé pour un client ; le reste de `/api/…` sert l'interface web (sessions, pas de contrat stable). **Pas d'authentification par jeton** (c'est la première chose à construire, §5). `docs/api.md` décrit une API *prévue* et est **périmé** : ne pas s'y fier |
| Comptes développeur Apple / Google Play | Le fondateur a dit qu'il les ouvrira. **Ne pas supposer qu'ils existent.** Pas de société (donc comptes au nom d'une personne) |

**Recommandation** (à faire valider par le fondateur avant de commencer) : créer un **nouveau projet** `Mes Projets/KIINI SAWA/mobile-v1/` avec son propre dépôt Git (`kiini-mobile-v1`), sans toucher à `mobile/`. Mettre à jour `README.md` (racine) et `docs/SUIVI-IA.md`.

## 3. Règles permanentes (le fondateur y tient)

1. **Déploiement** : ne déployer (`web/bin/ops/envoyer-hostinger.sh`) **que** si le fondateur écrit « production » ou « deploie ». Jamais enchaîné avec un commit. Après un déploiement, lui rappeler : `cd "/Users/arlais/Mes Projets/KIINI SAWA/web" && git push origin main`.
2. **Mots de passe et secrets** : ne jamais les demander ni les afficher ; ne jamais lire `bin/ops/hostinger.local`, `.env*` ni fichiers d'identifiants. Aucun secret dans le dépôt. Pour les tests, créer des comptes de test (§6).
3. **Pas de photo ni de nom du fondateur** dans l'application, les fiches des stores, les captures d'écran, les textes légaux. Jamais d'inventer témoignages, avis ou visages.
4. **Tutoiement/vouvoiement** : l'interface dit **« vous »** (le livre dit « tu »). Ton chaleureux, sans jargon ni anglais. Aucun code technique affiché brut (BE, DO, enums…).
5. **Polices et palette inchangées** : Playfair Display (titres) et Inter (texte) ; palette du livre (§7).
6. **Lexique** (`docs/lexique.md`) : un seul mot par chose (Rituels, pas Habitudes ; Agenda ; Partenaires ; Mes tests ; Note rapide ; Déclic ; Carnets…). Ne pas inventer de nouveaux mots.
7. **Charte** (`docs/charte-projet.md`) : non-régression fonctionnelle ; fidélité aux textes du fondateur et du livre (ne jamais reformuler le livre sans accord) ; transparence des données.
8. **Cadre doctrinal** des contenus bibliques : le Christ seul et son œuvre ; l'Ancien Testament pointe vers le Christ ; la grâce, jamais le légalisme ; version Louis Segond 1910. Aucun texte nouveau de ce genre sans validation.
9. **Tests et journal** : tout changement côté `web/` passe `php bin/phpunit` (≈ 290 tests) et s'accompagne d'une entrée dans `docs/SUIVI-IA.md`. Côté mobile : tests unitaires de la logique (file de synchro, formatage de dates, règles de statut) et vérification sur simulateur/appareil avant de dire « terminé ». Dire honnêtement ce qui n'a pas été vérifié sur un vrai appareil.
10. **Textes visibles** : ne pas écrire de texte en dur côté web ; utiliser `t('code', 'origine')` / `doc('code')` (module `Contenu`, `docs/modules/contenu.md`) pour que le fondateur les modifie dans l'administration. Côté mobile, regrouper tous les textes dans un seul fichier (`i18n/fr.ts`) pour pouvoir les alimenter plus tard depuis l'API.
11. **Confirmer avant tout acte irréversible ou public** : publication sur un store, envoi d'emails, suppression de données, création de comptes de production.

## 4. Contrainte majeure : les stores et le paiement

Le site vend l'offre PRO (EUR et FCFA) par **paiement manuel** (virement, Mobile Money validé par l'administrateur), sans société. **Apple (règle 3.1.1) et Google exigent en principe leur système d'achat intégré pour vendre un abonnement numérique dans l'application.** Conséquences à respecter tant que le fondateur n'a pas tranché :

- L'application **ne vend rien** et **n'affiche ni prix, ni lien de paiement, ni consigne de virement**. Elle affiche seulement l'état du compte (gratuit ou PRO) et, pour les fonctions réservées, un message neutre du type « Cette fonction fait partie de l'offre PRO, que vous pouvez gérer sur le site ». **Vérifier les règles en vigueur au moment de la soumission** (elles évoluent selon les pays) et **demander au fondateur** s'il accepte cette approche ou veut étudier les achats intégrés (commission, comptes et statut juridique requis).
- Apple exige aussi : **suppression du compte depuis l'application** (le site l'offre via `POST /profil/supprimer`, route `app_profile_delete` : à exposer en API), une **politique de confidentialité** (https://kiinisawa.com/confidentialite), une adresse de support, et la déclaration des données collectées. Les pages légales contiennent encore des « [À COMPLÉTER] » en attente de la structure juridique : **ne pas soumettre aux stores tant que `app:launch:check` signale des points bloquants** sans en parler au fondateur.

## 5. Ce qu'il faut construire côté Symfony (`web/`)

Créer un module `src/Mobile/` (`php bin/console app:module:create Mobile`). Reprendre les conventions de `src/Rapide/` (contrôleur mince, service testé, JSON, résultats par opération).

### 5.1 Authentification par jeton (priorité 1)

Aujourd'hui : sessions + cookie, connexion `/login`, e-mail à vérifier, **2e étape par code e-mail pour les administrateurs**, protection CSRF « par l'origine » (`Shared/Security/SameOriginRequestSubscriber` : les requêtes d'écriture sans en-tête `Origin` ou `Sec-Fetch-Site` sont refusées ; un client natif n'en envoie pas : **le contrôle doit être contourné uniquement pour les requêtes portant un jeton valide**, pas désactivé).

À construire (proposition, à adapter si un meilleur choix apparaît) :

- `POST /api/mobile/connexion` `{email, password, appareil}` → `{accessToken, refreshToken, expiresIn, user}`. Réutiliser le hachage et les vérifications existants (compte vérifié ; compte suspendu refusé). **Refuser les comptes administrateurs** tant que la 2e étape n'est pas prévue pour le mobile.
- `POST /api/mobile/jeton` (rafraîchissement, **jeton à usage unique avec rotation**), `POST /api/mobile/deconnexion` (révocation).
- Jeton d'accès court (15 à 30 min), jeton de rafraîchissement long (60 jours), **stocké haché en base** (entité `MobileToken`, appareil, dernière utilisation, révocable). Pare-feu Symfony dédié `^/api/mobile` avec authenticator `Bearer`.
- **Limitation de débit** par IP et par compte (voir `config/packages/rate_limiter.yaml`, à compléter) ; messages d'erreur génériques (pas d'indication sur l'existence d'un compte).
- Inscription et mot de passe oublié : dans un premier temps, **ouvrir le site dans le navigateur** (`expo-web-browser`).
- Tests : connexion, mauvais mot de passe, jeton expiré, rotation, révocation, compte non vérifié, requête sans `Origin` acceptée **seulement** avec jeton valide.

### 5.2 Points d'accès par étape

Format : JSON, dates ISO 8601, erreurs `{error: "message en français"}`, codes HTTP corrects, `Cache-Control: no-store`. Versionner : `/api/mobile/v1/...`.

- **Étape 2 (quotidien)** : reprendre tels quels `/api/rapide/aujourdhui` et `/api/rapide/sync` (voir §6) sous le pare-feu jeton. Ajouter : liste des rituels (création/modification/suppression), journal complet (liste paginée, carnets, suppression), **enregistrement du jeton de notification** (`POST /api/mobile/notifications/appareil` avec le jeton Expo Push ; nouvelle entité à côté de `Notification\Entity\PushSubscription`, qui ne gère aujourd'hui que le Web Push/VAPID du navigateur ; `Notification\Service\PushSender` est à étendre pour envoyer via l'API Expo Push, avec les mêmes préférences et anti-doublon que `Notifier`).
- **Étape 3 (plans)** : lecture du Plan de Vie (But de Dieu, valeurs, vision, objectifs) et des autres plans ; progression par plan (`PlanDeVie\Service\PlanProgress`) ; « Ma prochaine étape » (`Dashboard\Service\NextStep`, déjà dans le résumé du jour). L'édition reste sur le site au début.
- **Étape 4 (tests)** : liste des tests, questions, enregistrement des réponses, **rapport** (`Personnalite\Psychometrie\ReportBuilder` produit les données ; PDF via `/mes-tests/rapport/{id}/pdf` : l'ouvrir dans le navigateur ou le télécharger avec le jeton).
- **Étape 5 (revues, agenda, partenaires)** : revue de la semaine et du mois, agenda, partenaires.

Documenter chaque point d'accès dans un nouveau fichier `docs/api-mobile.md` (**remplacer** l'actuel `docs/api.md`, périmé, en le signalant au fondateur).

## 6. Contrat existant : le mode rapide (à réutiliser tel quel)

Code : `web/src/Rapide/` ; documentation : `docs/modules/rapide.md` ; tests : `web/tests/Rapide/QuickDayTest.php`.

- `GET /api/rapide/aujourdhui` → `{date, name, next:{title, eyebrow, text, cta, url}, habits:[{id,title,frequency (DAILY|WEEKLY|MONTHLY),done}], review:null|{intention,kept,energy,mood,stress,gratitude}, journal:[{id,text,type,at}], types:{REFLECTION:"Pensée",…}}`.
- `POST /api/rapide/sync` `{ops:[…]}` → `{results:{uid:"ok"|"deja"|"refusee"}, snapshot:{…}}`. Chaque opération : `uid` (8 à 64 caractères `[A-Za-z0-9-]`, créé par l'appareil, **idempotence**), `type`, `date` (AAAA-MM-JJ du téléphone), `at` (ISO), plus :
  - `habit.log` : `habit` (id), `status` (booléen) ;
  - `review.save` : `intention`, `kept` (true/false/null), `energy`, `mood`, `stress` (1 à 5 ou null), `gratitude` ;
  - `journal.add` : `content`, `entryType` (REFLECTION, IDEA, RESOLUTION, MILESTONE, TEMOIGNAGE, INSIGHT).
- Règles serveur : **dernière modification gagne** ; un jour est accepté de J-7 à J+1 ; 100 opérations par envoi au plus ; le bilan du jour est **mis à jour**, jamais dupliqué (une intention vide n'efface pas l'ancienne) ; une opération `refusee` est à **abandonner** (ne pas la renvoyer) ; `ok` et `deja` sont définitifs.
- Côté client : **file persistante** des opérations (SQLite ou stockage sécurisé), affichage immédiat (« optimiste ») par superposition de la file sur le dernier état reçu, envoi au retour du réseau et à l'ouverture, indicateur « n en attente d'envoi ». **Le client web est une implémentation de référence** : `web/templates/rapide/index.html.twig` (JavaScript commenté, mêmes règles). Reprendre la logique `view()`, `enqueue()` (avec fusion des opérations successives sur le même objet), `sync()`.

## 7. Design et expérience

- Palette (`web/templates/base.html.twig`, `:root`) : fond `#F7F8F5`, fond secondaire `#EEF1EC`, carte `#FFFFFF`, bordure `#E1E6DF`, texte `#1B1B1B` / `#374238` / `#66706A`, **vert du livre `#2E6B53`** (foncé `#1F4D3A`), **or `#C8962E`** (texte or `#9A6B12`), sauge `#7A9E7E`. Sens : succès `#3A7D52`, attention `#B7791F`, danger `#B3402A`, information `#3B6E8F`, couple `#B5562F`.
- Plans : Vie `#C8962E`, Croissance `#1F4D3A`, Développement personnel `#2E6B53`, Hygiène de vie `#5D7368`, Intendance `#5E8463`, Transmission `#A87A1F` (chacun a une teinte « soft » dans `base.html.twig`).
- Polices : Playfair Display (titres, citations), Inter (texte) via `expo-font` ; icônes `lucide-react-native` (mêmes noms que le site : `compass`, `sprout`, `wrench`, `heart-pulse`, `hand-coins`, `hand-heart`). **Aucun émoji dans l'interface.**
- Les 6 plans : ordre, noms et formules dans `docs/lexique.md` (source unique dans le code : `PlanDeVie\Service\PlanCatalog::PLANS`) ; **à exposer par l'API plutôt que recopier**.
- Mode sombre : facultatif au début ; si fait, redéfinir tous les jetons.
- Accessibilité : cibles tactiles ≥ 44 pt, contrastes AA, texte redimensionnable, libellés pour lecteurs d'écran. **Les libellés sous les icônes de la barre du bas sont obligatoires** (demande du fondateur).
- Navigation (comme le site, `Shared/Twig/NavigationExtension`) : barre du bas **Aujourd'hui, Mes plans, Rituels, Journal, Menu** ; le menu regroupe les six rubriques du site (Aujourd'hui, Me connaître, Mes plans, Mon quotidien, Ensemble, La Méthode) et le compte.
- Écran d'accueil = « Aujourd'hui » : **Ma prochaine étape** en tête, puis rituels du jour, bilan du jour, note rapide.

## 8. Étapes et critères d'acceptation

1. **Fondations** : projet Expo (TypeScript, Expo Router, SDK à jour au moment du démarrage), polices, thème, `i18n/fr.ts`, client API avec rafraîchissement automatique du jeton, stockage des jetons dans `expo-secure-store`, écran de connexion. *Critère* : se connecter avec un compte de test, rester connecté après redémarrage, se déconnecter (jetons révoqués côté serveur, cache local effacé).
2. **Aujourd'hui, rituels, bilan du jour, journal** hors ligne (file §6). *Critère* : mode avion → cocher, écrire, enregistrer le bilan → réseau rétabli → tout apparaît sur le site, sans doublon ; test d'un renvoi après coupure.
3. **Rappels** : notifications locales et distantes (`expo-notifications`), autorisation demandée au bon moment avec explication, préférences respectées, touche → ouvre le bon écran. *Critère* : testé sur iPhone et Android réels (les notifications ne fonctionnent pas dans Expo Go sur toutes les plateformes : utiliser un *development build* EAS).
4. **Mes plans** (lecture), cercle des plans, progression.
5. **Mes tests** et rapports, synthèse « Mon profil KIINI SAWA ».
6. **Revues de la semaine et du mois, agenda, partenaires.**
7. **Publication** : voir §9.

Chaque étape : vérifier sur simulateur **et** un appareil réel, mettre à jour ce document et `docs/SUIVI-IA.md`, présenter une démonstration au fondateur avant de passer à la suivante.

## 9. Publication (quand le fondateur le décide)

À préparer avec lui, **sans rien publier seul** : comptes Apple Developer (99 €/an) et Google Play (25 € une fois) ; identifiants d'application définitifs (remplacer `com.anonymous.kiini-mobile`) ; icônes et écrans de lancement (reprendre `web/public/icons/` et les couleurs de la marque) ; captures d'écran sans photo du fondateur ; description (reprendre le slogan et la présentation de la vitrine) ; politique de confidentialité et support ; étiquettes de confidentialité (Apple) et section sécurité des données (Google) ; classification d'âge ; test interne (TestFlight / test interne Play) avant toute mise en ligne publique ; EAS Build/Submit. Garder une procédure écrite dans `docs/mobile-publication.md`.

## 10. Travailler en local

```bash
cd "/Users/arlais/Mes Projets/KIINI SAWA/web"
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console app:install --admin-email=vous@exemple.com   # données de départ
php -S 127.0.0.1:8010 -t public                               # ou la configuration « web-v1 » de .claude/launch.json
php bin/console app:create-user compte-test@kiinisawa.test "mot-de-passe-de-test-à-choisir"   # compte de test local
php bin/phpunit                                              # base de test dédiée (suffixe _test), voir AGENTS.md §5
```

- Un téléphone ou un simulateur joint le serveur local par l'adresse IP de l'ordinateur (pas `localhost` sur un vrai téléphone) ; en dev, les e-mails ne partent pas : le code de vérification s'affiche dans un bandeau « [dev] ».
- `app:create-user` crée un compte déjà vérifié (option `--admin` pour un administrateur) ; il n'a pas d'option PRO : pour voir les fonctions PRO en local, lui donner un abonnement depuis l'administration locale (compte administrateur de test), ou s'inspirer de `createUser(pro: true)` dans `tests/Compte/AccountTestTrait.php`.
- Pièges connus : `DailyLog` est dans `App\PlanDHygiene\Entity` (pas `Bilan`) ; en test, la collection `habits` d'un utilisateur créé dans le même test est vide en mémoire tant que l'EntityManager n'est pas vidé (`clear()`) ; MariaDB réserve certains mots (`value`…) ; les tests `SecurityGuardsTest` listent les routes publiques : toute nouvelle route publique doit y être déclarée.

## 11. À ne pas faire

- Réutiliser l'ancienne app `mobile/` ou son SQLite/Supabase ; appeler `web-v0-nextjs`.
- Ajouter des routes publiques ou affaiblir le contrôle d'origine pour tout le monde.
- Stocker des mots de passe ou des jetons hors de `expo-secure-store` ; journaliser des données personnelles.
- Dupliquer côté mobile des textes ou des règles du livre qui existent côté serveur (les demander à l'API).
- Afficher des prix ou un moyen de paiement (§4) ; promettre des fonctions non faites ; inventer des témoignages.
- Publier, déployer, envoyer des e-mails réels ou toucher aux données de production sans demande explicite du fondateur.

## 12. Questions à poser au fondateur avant l'étape 1

1. Nouveau dossier `mobile-v1/` + nouveau dépôt (recommandé) ou autre choix ?
2. Paiement et PRO dans l'application : approche « consultation seulement » (§4) acceptée ?
3. Comptes Apple et Google : dates prévues ?
4. Nom affiché sur les stores (compte personnel : son nom apparaîtrait comme éditeur) : acceptable, ou attendre la structure juridique ?
5. Langues : français uniquement au lancement (par défaut) ?
6. Mode sombre : au lancement ou plus tard ?

## 13. Où trouver quoi

| Besoin | Fichier |
|---|---|
| Guide général du dépôt web | `web/AGENTS.md` |
| Charte, lexique, design | `web/docs/charte-projet.md`, `web/docs/lexique.md`, `web/docs/design.md` |
| Journal des chantiers (à tenir à jour) | `web/docs/SUIVI-IA.md` |
| Mode rapide (modèle de synchro hors ligne) | `web/docs/modules/rapide.md`, `web/src/Rapide/`, `web/templates/rapide/index.html.twig` |
| Textes modifiables | `web/docs/modules/contenu.md` |
| Notifications (e-mails, Web Push) | `web/src/Notification/` (`Notifier`, `PushSender`, `ReminderService`) |
| Plans, progression, prochaine étape | `web/src/PlanDeVie/Service/PlanCatalog.php`, `PlanProgress.php`, `web/src/Dashboard/Service/NextStep.php` |
| Tests de personnalité et rapports | `web/src/Personnalite/`, `web/docs/modules/personnalite.md` |
| Le livre (chapitres publics et complets) | `web/content/livre/` ; manuscrit source `livre-et-ressources/` (texte de l'auteur : ne pas réécrire) |
| Mise en ligne, hébergement | `web/docs/mise-en-ligne.md` (le fondateur déploie ; ne pas toucher aux accès) |
| Organisation de tous les projets | `README.md` à la racine de `KIINI SAWA/` |
