# Guide pour les IA (et les développeurs) — KIINI SAWA web

À lire en entier avant toute modification. Le produit est en production : **https://app.kiinisawa.com**.

## 1. Le produit en bref

KIINI SAWA est une application de planification de vie **chrétienne assumée**, en français, pour l'Europe et l'Afrique
francophone. La méthode vient d'un livre (`docs/livre/`) : on part de l'Être (raison d'être, valeurs, dons) pour aller
vers le Faire (5 plans, rituels, revues).

- **5 plans** : Plan de Vie, Plan de Croissance, Plan de Développement, Plan de Transmission, Plan d'Hygiène de Vie.
- **Autour** : rituels, journal, agenda, bilan (revues de la semaine et du mois), partenaires de redevabilité, tests
  de personnalité, espace couple, organisations (églises, associations), la méthode (chapitres du livre).
- **Monétisation** : offre gratuite + PRO (mensuel, annuel, Duo couple, places d'organisation), en **EUR et FCFA**.
  Paiements **manuels** (virement, Mobile Money) validés par l'administrateur ; pas de paiement en ligne tant que la
  structure juridique n'existe pas.

## 2. Règles à respecter absolument

1. **Charte** : `docs/charte-projet.md` (termes intouchables, non-régression fonctionnelle, fidélité au texte de
   l'utilisateur). Ne jamais supprimer une fonctionnalité ni reformuler un texte de l'utilisateur sans accord.
2. **Lexique** : `docs/lexique.md`. Un seul mot par chose (Rituels, Agenda, Partenaires, Mes tests, Mes stratégies,
   score de cohérence, Être/Faire/Avoir/Donner…). `tests/Shared/LexiconTest.php` refuse les termes abandonnés.
3. **Système visuel** : `docs/design.md`. Palette en variables CSS (`templates/base.html.twig`), composants `ks-…`
   (`assets/styles/app.css`), en-tête de page `templates/shared/_page_header.html.twig`. Pas de couleur en dur dans
   une page de l'application ; une seule action principale par écran.
4. **Interface 100 % en français**, ton chaleureux, sans jargon ni anglais. Les codes techniques (BE, DO, enums…)
   ne s'affichent jamais bruts (filtre Twig `categorie_objectif` pour les objectifs).
5. **Tests** : tout changement passe `php bin/phpunit` (≈ 140 tests). Ajouter un test pour chaque correction.
6. **Secrets** : jamais dans le dépôt. Ils vivent sur le serveur dans `.env.prod.local` (voir §6).
7. **Marques** : les tests s'inspirent du Big Five, de Jung, de DISC, de l'ennéagramme, mais n'emploient pas les
   marques déposées (MBTI®…) autrement qu'en référence nominative. Noms de types propres à KIINI SAWA.

## 3. Stack

Symfony 8.1, PHP 8.4+ (8.5 en local et en production), Doctrine ORM 3, MySQL en local / **MariaDB 11.8 en production**,
AssetMapper + importmap (pas de Node), Tailwind (symfonycasts/tailwind-bundle), Stimulus, Turbo, Alpine.js, Lucide,
Mermaid, Chart.js, Dompdf (PDF), Parsedown (livre). Polices et bibliothèques auto-hébergées (CSP sans hôte externe).

## 4. Organisation du code

Un **module par dossier** : `src/<Module>/{Controller,Entity,Repository,Service,Command,Twig,...}`, gabarits dans
`templates/<module>/`, tests dans `tests/<Module>/`, documentation dans `docs/modules/<module>.md`.

- Créer un module : `php bin/console app:module:create NomDuModule` ; une entité : `app:module:entity`.
- Modules principaux : `Compte`, `Dashboard`, `PlanDeVie` (+ `PlanDeCroissance`, `PlanDeDeveloppement`,
  `PlanDeTransmission`, `PlanDHygiene`), `Rituels`, `Journal`, `Planner` (agenda), `Bilan`, `Social` (partenaires),
  `Personnalite` (tests, dont `Psychometrie/` : banque IPIP-NEO, scores, normes), `Couple`, `Organisation`,
  `Abonnement`, `Notification`, `Methode` (livre), `Admin`, `Shared`.
- Droits PRO : `App\Abonnement\Service\Entitlements` (`can`, `require`, `requireBelowLimit`) ; une exception
  `ProRequiredException` redirige vers `/abonnement`.
- Notifications et emails : `App\Notification\Service\Notifier` (préférences, anti-doublon, désabonnement signé).
- Sécurité : contrôle d'accès dans `config/packages/security.yaml` (liste des pages publiques), vérification de
  l'email obligatoire, **connexion en deux étapes des administrateurs** (`Compte/Security/AdminSecondFactorSubscriber`),
  protection CSRF par l'origine, en-têtes de sécurité (`Shared/EventSubscriber/SecurityHeadersSubscriber`).

Documentation détaillée : `docs/index.md`, `docs/architecture.md`, `docs/database.md`, `docs/modules/*.md`.
Historique des décisions et chantiers : `docs/plan-v1.md`, `docs/audit-2026-11-16.md`.

## 5. Travailler en local

```bash
composer install
php bin/console doctrine:migrations:migrate -n
php bin/console app:install --admin-email=vous@exemple.com   # données de départ + administrateur
php bin/console tailwind:build --watch                        # styles
php -S 127.0.0.1:8010 -t public                               # ou la configuration « web-v1 » de .claude/launch.json
```

`.env.local` (non versionné) porte `DATABASE_URL` (MySQL local). En développement les emails ne partent pas : le code
de vérification s'affiche dans un bandeau « [dev] ».

**Tests** : base dédiée (suffixe `_test`) avec un compte `admin@kiinisawa.com` dont dépendent de nombreux tests :

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate -n --env=test
php bin/console app:create-user admin@kiinisawa.com "mot-de-passe-de-test" --admin --env=test
php bin/phpunit
```

Migrations : une migration de départ (`Version20261116060104`) puis des migrations incrémentales. Toujours vérifier
`php bin/console doctrine:schema:validate` après une modification d'entité.

## 6. Production (Hostinger, hébergement mutualisé)

Guide complet : `docs/mise-en-ligne.md` (section 3). En résumé :

- Déployer depuis `web/` : `bin/ops/envoyer-hostinger.sh` (rsync puis `bin/ops/mise-a-jour.sh` sur le serveur :
  dépendances, assets, migrations, cache). Les accès SSH sont dans `bin/ops/hostinger.local` (non versionné) : sans
  eux, on ne peut pas déployer, c'est voulu.
- Secrets du serveur : `.env.prod.local`, écrits par `bin/ops/configurer-base.sh` et `bin/ops/configurer-emails.sh`
  (saisie masquée). Ne jamais demander ni afficher un mot de passe.
- Emails : Brevo (SMTP), envoyés immédiatement (`MESSENGER_TRANSPORT_DSN=sync://`, pas de processus permanent possible).
- Tâches planifiées (hPanel, pas de crontab en SSH) : `bin/ops/cron-rappels.sh`, `bin/ops/cron-sauvegarde.sh`.
- Journal d'erreurs : `var/log/prod-AAAA-MM-JJ.log` sur le serveur.
- Contrôle avant ouverture : `php bin/console app:launch:check`.

**Pièges connus du serveur** :
- **MariaDB** : certains mots sont réservés (`value` → table `life_value`). `tests/Shared/LaunchReadinessTest.php`
  vérifie le schéma. Tester toute requête SQL écrite à la main.
- PHP en ligne de commande vaut 8.3 par défaut : utiliser `/opt/alt/php85/usr/bin/php`.
- `proc_open`, `exec`… désactivés : les commandes d'installation tournent avec `-d disable_functions=` ; `/tmp` n'est
  pas exécutable (Tailwind utilise `TMPDIR=~/tmp`). Le site lui-même garde ces restrictions.

## 7. Le livre

Manuscrit source : `docs/livre/*.md` (texte de l'auteur, à ne pas réécrire sans accord). Les chapitres affichés dans
l'application sont copiés dans `content/livre/` par `php bin/console app:livre:sync`. Les 3 premiers chapitres sont
publics (`BookLibrary::PUBLIC_CHAPTERS`).

## 8. Ce qui reste ouvert

- Structure juridique à créer : les passages « [À COMPLÉTER] » des pages légales et des reçus en dépendent
  (`app:launch:check` les compte). Ne pas les inventer.
- Paiement en ligne (carte, Mobile Money automatique) : après l'immatriculation.
- API de synchronisation pour l'application mobile (dépôt séparé `kiini-mobile`).
