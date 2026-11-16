# Mise en ligne de KIINI SAWA

Ce guide décrit la mise en ligne de la version web (Symfony). Le contrôle
`php bin/console app:launch:check` liste à tout moment ce qui manque.

## 1. Ce qu'il faut choisir (décisions du porteur du projet)

| Décision | Recommandation | Pourquoi |
|---|---|---|
| **Société** | Immatriculer la structure qui encaisse | Obligatoire pour les mentions légales, les reçus puis les factures, et plus tard le paiement par carte ou Mobile Money automatique. |
| **Hébergement** | **Retenu : Clever Cloud** (section 3). Sinon, un hébergeur en Europe, avec PHP 8.4 ou plus, MySQL 8 et tâches planifiées : une plateforme managée (ex. Clever Cloud, Platform.sh) ou un serveur privé (ex. OVHcloud, Scaleway, Hetzner, Infomaniak) | Données en Europe (RGPD). La plateforme managée évite d'administrer un serveur. |
| **Nom de domaine** | Ex. `kiinisawa.com` pour la présentation et `app.kiinisawa.com` pour l'application | Le domaine sert aussi à l'adresse d'envoi des emails. |
| **Envoi d'emails** | Un fournisseur transactionnel européen (ex. Brevo, Mailjet) ou Postmark | Codes de vérification, mots de passe oubliés, rappels, reçus : indispensable dès le premier jour. |
| **Paiements** | Renseigner virement et/ou Mobile Money dans l'administration | Sinon le paiement reste masqué. |

## 2. Variables d'environnement (serveur)

| Variable | Exemple | Rôle |
|---|---|---|
| `APP_ENV` | `prod` | Mode production. |
| `APP_DEBUG` | `0` | Pas d'outils de débogage. |
| `APP_SECRET` | 64 caractères aléatoires | `php -r "echo bin2hex(random_bytes(32));"`. Ne jamais la partager. |
| `DEFAULT_URI` | `https://app.kiinisawa.com` | Liens absolus dans les emails et les tâches planifiées. |
| `DATABASE_URL` | `mysql://user:motdepasse@hote:3306/kiini?serverVersion=8.4.0&charset=utf8mb4` | Base de données (automatique sur Clever Cloud). |
| `MAILER_DSN` | fourni par le prestataire d'emails | Envoi des emails. |
| `MAILER_FROM` | `"KIINI SAWA <bonjour@kiinisawa.com>"` | Expéditeur (domaine vérifié chez le prestataire : SPF, DKIM). |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | File d'attente des emails. |
| `TRUSTED_PROXIES` | adresse du proxy de l'hébergeur, ou `REMOTE_ADDR` | Reconnaissance de HTTPS et des vraies adresses IP. |

## 3. Hébergement retenu : Clever Cloud (pas à pas)

Plateforme managée française (données en France) : pas de serveur à administrer, HTTPS automatique,
redémarrage automatique. Tout est déjà préparé dans le projet :

| Fichier | Rôle |
|---|---|
| `clevercloud/post_build.sh` | Après chaque déploiement : assets, cache, migrations de la base. |
| `clevercloud/cron.json` + `cron_reminders.sh` | Rappels chaque jour à 8 h (heure de Paris). |
| `.env.prod` | Construit `DATABASE_URL` à partir de l'add-on MySQL ; proxys de confiance. |
| `public/.htaccess` | Routage Apache vers l'application. |
| Sessions en base (table `sessions`) | Les utilisateurs restent connectés après un déploiement. |

### 3.1 Ce que vous faites une seule fois

1. **Créer le compte** sur clever-cloud.com (vous-même) et ajouter un moyen de paiement.
2. **Installer l'outil en ligne de commande** puis vous connecter :
   ```bash
   npm install -g clever-tools
   clever login
   ```
3. **Créer l'application et la base**, depuis le dossier `web/` :
   ```bash
   clever create --type php kiini-sawa --region par
   clever addon create mysql-addon kiini-sawa-db --plan xs_sml --region par --link kiini-sawa
   ```
   Le plan `xs_sml` (dédié) inclut une **sauvegarde quotidienne** conservée 7 jours. Le plan `dev`
   (gratuit) est réservé aux essais : pas de sauvegarde, ne jamais y mettre de vrais utilisateurs.
   Les noms exacts des plans et la cible DNS sont donnés par `clever addon providers show mysql-addon`
   et par la console Clever Cloud (onglet « Domain names ») : ils priment sur ce guide.
4. **Variables d'environnement** :
   ```bash
   clever env set CC_PHP_VERSION 8.5
   clever env set CC_WEBROOT /public
   clever env set CC_POST_BUILD_HOOK ./clevercloud/post_build.sh
   clever env set CC_WORKER_COMMAND "php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M"
   clever env set CC_WORKER_RESTART always
   clever env set APP_ENV prod
   clever env set APP_DEBUG 0
   clever env set APP_SECRET "$(openssl rand -hex 32)"
   clever env set DEFAULT_URI https://app.kiinisawa.com
   clever env set MAILER_DSN "…fourni par le prestataire d'emails…"
   clever env set MAILER_FROM "KIINI SAWA <bonjour@kiinisawa.com>"
   ```
   Vérifier que les variables de l'add-on (`MYSQL_ADDON_HOST`, `MYSQL_ADDON_DB`…) apparaissent bien
   avec `clever env`. Si leurs noms diffèrent, définir directement `DATABASE_URL`
   (`mysql://utilisateur:motdepasse@hote:port/base?serverVersion=8.4.0&charset=utf8mb4`).
5. **Premier déploiement** (le code doit être commité) :
   ```bash
   clever deploy
   clever logs          # suivre la construction
   ```
6. **Créer le compte administrateur** (le mot de passe est demandé sans être affiché) :
   ```bash
   clever ssh
   cd $APP_HOME && php bin/console app:install --admin-email=vous@domaine.com
   php bin/console app:launch:check
   ```
7. **Nom de domaine** : `clever domain add app.kiinisawa.com`, puis chez le registraire un
   enregistrement CNAME `app` vers `domain.par.clever-cloud.com.` Le certificat HTTPS est automatique.
8. **Surveillance** : faire vérifier `https://app.kiinisawa.com/sante` toutes les 5 minutes (UptimeRobot, Better Stack).

### 3.2 Mettre à jour une version en ligne

```bash
git commit -am "…"
clever deploy
```

Les migrations s'appliquent toutes seules ; le worker d'envoi des emails redémarre avec le nouveau code.
En cas de souci : `clever restart --commit <commit-précédent>` revient à la version précédente.

## 4. Autre hébergeur : déploiement manuel (première fois)

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console app:install --admin-email=vous@domaine.com   # demande le mot de passe, sans l'afficher
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
php bin/console app:launch:check
```

Le serveur web doit servir le dossier `public/` en HTTPS (certificat Let's Encrypt, souvent automatique chez l'hébergeur).

## 5. Processus permanents et tâches planifiées (hors Clever Cloud)

| Quoi | Commande | Fréquence |
|---|---|---|
| Envoi des emails | `php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M` | En continu (redémarré automatiquement par l'hébergeur ou systemd/supervisor) |
| Rappels (revues, rituels, échéances, partenaires, fin d'essai et d'abonnement) | `php bin/console app:reminders:send` | Chaque jour, ex. à 8 h |
| Sauvegarde de la base | `bin/ops/sauvegarde.sh /chemin/sauvegardes` | Chaque nuit, avec copie hors du serveur |

Exemple de crontab :

```
0 8 * * * cd /var/www/kiini/web && php bin/console app:reminders:send >> var/log/rappels.log 2>&1
0 3 * * * cd /var/www/kiini/web && bin/ops/sauvegarde.sh /var/backups/kiini >> var/log/sauvegarde.log 2>&1
```

Exemple de service systemd pour l'envoi des emails (`/etc/systemd/system/kiini-messenger.service`) :

```
[Unit]
Description=KIINI SAWA - envoi des emails
After=network.target mysql.service

[Service]
WorkingDirectory=/var/www/kiini/web
ExecStart=/usr/bin/php bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
Restart=always
User=www-data

[Install]
WantedBy=multi-user.target
```

## 6. Mettre à jour une version déjà en ligne (hors Clever Cloud)

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console tailwind:build --minify && php bin/console asset-map:compile
php bin/console cache:clear
php bin/console messenger:stop-workers   # le worker redémarre avec le nouveau code
```

Toujours faire une sauvegarde avant une mise à jour qui contient des migrations.

## 7. Après la mise en ligne

- **Surveillance** : faire vérifier `https://app.../sante` toutes les 5 minutes par un service de surveillance (ex. UptimeRobot, Better Stack) : réponse 200 = application et base en ligne.
- **Journaux** : en production, les erreurs sont écrites sur la sortie d'erreur (récupérée par l'hébergeur).
- **Restauration d'une sauvegarde** : `gunzip -c fichier.sql.gz | mysql -u utilisateur -p base`.
- **Référencement** : `robots.txt` et `sitemap.xml` sont générés automatiquement ; seules les pages publiques sont indexables.

## 8. Ce qui reste hors de cette version

- **Application mobile** : elle synchronise avec l'ancienne version (Next.js). Elle ne fonctionnera avec la V1 qu'après la phase 7 (API de synchronisation). Au lancement, la V1 est une application web, utilisable sur téléphone dans le navigateur.
- **Paiement en ligne** (carte, Mobile Money automatique) : après l'immatriculation, voir `docs/modules/abonnement.md`.
- **Reprise des données de l'ancienne version** : décidée pour plus tard.

## 9. Historique des migrations

L'historique hérité ne reconstruisait pas le schéma complet sur une base vide (colonnes manquantes).
Il a été remplacé le 16/11/2026 par une migration de départ unique (`Version20261116060104`), générée
depuis le modèle et vérifiée sur une base vide, suivie de la table des sessions (`Version20261116120000`). Les bases existantes ont été marquées à jour sans perte de données.
