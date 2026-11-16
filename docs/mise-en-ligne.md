# Mise en ligne de KIINI SAWA

Ce guide décrit la mise en ligne de la version web (Symfony). Le contrôle
`php bin/console app:launch:check` liste à tout moment ce qui manque.

## 1. Ce qu'il faut choisir (décisions du porteur du projet)

| Décision | Recommandation | Pourquoi |
|---|---|---|
| **Société** | Immatriculer la structure qui encaisse | Obligatoire pour les mentions légales, les reçus puis les factures, et plus tard le paiement par carte ou Mobile Money automatique. |
| **Hébergement** | **Retenu : Hostinger Business Web Hosting** (section 3). Sinon, un hébergeur en Europe, avec PHP 8.4 ou plus, MySQL 8 et tâches planifiées : une plateforme managée (ex. Clever Cloud, Platform.sh) ou un serveur privé (ex. OVHcloud, Scaleway, Hetzner, Infomaniak) | Données en Europe (RGPD). La plateforme managée évite d'administrer un serveur. |
| **Nom de domaine** | Ex. `kiinisawa.com` pour la présentation et `app.kiinisawa.com` pour l'application | Le domaine sert aussi à l'adresse d'envoi des emails. |
| **Envoi d'emails** | Un fournisseur transactionnel européen (ex. Brevo, Mailjet) ou Postmark | Codes de vérification, mots de passe oubliés, rappels, reçus : indispensable dès le premier jour. |
| **Paiements** | Renseigner virement et/ou Mobile Money dans l'administration | Sinon le paiement reste masqué. |

## 2. Variables d'environnement (serveur)

Sur Hostinger, elles vont dans le fichier `.env.prod.local` du serveur (modèle : `bin/ops/env.prod.local.exemple`).

| Variable | Exemple | Rôle |
|---|---|---|
| `APP_ENV` | `prod` | Mode production. |
| `APP_DEBUG` | `0` | Pas d'outils de débogage. |
| `APP_SECRET` | 64 caractères aléatoires | `php -r "echo bin2hex(random_bytes(32));"`. Ne jamais la partager. |
| `DEFAULT_URI` | `https://app.kiinisawa.com` | Liens absolus dans les emails et les tâches planifiées. |
| `DATABASE_URL` | `mysql://user:motdepasse@hote:3306/kiini?serverVersion=8.4.0&charset=utf8mb4` | Base de données. Sur MariaDB : `serverVersion=11.8.2-MariaDB` (la version réelle). |
| `MAILER_DSN` | fourni par le prestataire d'emails | Envoi des emails. |
| `MAILER_FROM` | `"KIINI SAWA <bonjour@kiinisawa.com>"` | Expéditeur (domaine vérifié chez le prestataire : SPF, DKIM). |
| `MESSENGER_TRANSPORT_DSN` | `sync://` en mutualisé, `doctrine://default?auto_setup=0` avec un worker | Envoi immédiat, ou file d'attente des emails. |
| `TRUSTED_PROXIES` | adresse du proxy de l'hébergeur, ou `REMOTE_ADDR` | Reconnaissance de HTTPS et des vraies adresses IP. |

## 3. Hébergement retenu : Hostinger Business Web Hosting (pas à pas)

Hébergement mutualisé géré depuis hPanel : pas de serveur à administrer, HTTPS gratuit,
sauvegardes quotidiennes. Choisir un **centre de données en Europe** (France ou Pays-Bas) pour le RGPD.

Tout est préparé dans le projet :

| Fichier | Rôle |
|---|---|
| `bin/ops/envoyer-hostinger.sh` | Depuis votre ordinateur : envoie le code puis lance la mise à jour sur le serveur. |
| `bin/ops/mise-a-jour.sh` | Sur le serveur : dépendances, assets, migrations, cache. |
| `bin/ops/env.prod.local.exemple` | Modèle du fichier de secrets du serveur (`.env.prod.local`). |
| `.env.prod` | Réglages de production sans secret : emails envoyés immédiatement (pas de worker possible en mutualisé). |
| `public/.htaccess` | Routage vers l'application (LiteSpeed lit ce fichier). |
| Sessions en base (table `sessions`) | Les utilisateurs restent connectés après une mise à jour. |

### 3.1 Dans hPanel (une seule fois)

1. **Site web** : ajouter le domaine (ou un sous-domaine, ex. `app.kiinisawa.com`).
2. **Avancé › Configuration PHP** : choisir **PHP 8.4** (ou plus) ; vérifier que `intl`, `pdo_mysql`, `gd`,
   `zip`, `mbstring` sont cochées ; `memory_limit` à 256M au moins.
3. **Bases de données › MySQL** : créer la base et son utilisateur. Noter le nom, l'utilisateur, le mot de passe.
   Dans phpMyAdmin, onglet « Serveur », relever la **version** (ex. `11.8.2-MariaDB`).
4. **Avancé › Accès SSH** : activer SSH, noter l'utilisateur (`u123456789`), l'adresse IP et le port (souvent 65002).
   Ajouter votre clé SSH publique (`cat ~/.ssh/id_ed25519.pub` sur votre Mac ; sinon `ssh-keygen -t ed25519`).
5. **Sécurité › SSL** : installer le certificat gratuit puis activer « Forcer HTTPS ».
6. Ne pas activer le CDN Hostinger au début (il faudrait alors changer `TRUSTED_PROXIES`).

### 3.2 Sur le serveur, par SSH (une seule fois)

Organisation retenue (16/11/2026) : le code est dans `~/kiini`, hors de tout dossier public, et le dossier
public du site pointe vers `~/kiini/public` :

```bash
ssh -p 65002 u340339661@194.36.184.161
cd ~/domains/app.kiinisawa.com
mv public_html public_html.origine
ln -s /home/u340339661/kiini/public public_html
```

En ligne de commande, `php` est en 8.3 : utiliser `/opt/alt/php85/usr/bin/php` (même version que celle
choisie dans hPanel pour le site).

Créer les secrets à partir du modèle (après le premier envoi, étape 3.3) :

```bash
cd ~/kiini
cp bin/ops/env.prod.local.exemple .env.prod.local
nano .env.prod.local     # APP_SECRET, DEFAULT_URI, DATABASE_URL (avec la version relevée), MAILER_DSN
```

### 3.3 Depuis votre ordinateur : premier envoi

Dans le dossier `web/`, créer `bin/ops/hostinger.local` (jamais versionné) :

```bash
HOSTINGER_SSH="u340339661@194.36.184.161"
HOSTINGER_PORT=65002
HOSTINGER_DIR="kiini"
HOSTINGER_PHP="/opt/alt/php85/usr/bin/php"
```

Puis :

```bash
bin/ops/envoyer-hostinger.sh
```

Le premier envoi s'arrête en demandant `.env.prod.local` : le créer (étape 3.2) puis relancer.
Ensuite, sur le serveur, créer le compte administrateur (mot de passe demandé sans être affiché) :

```bash
cd ~/kiini
PHP=/opt/alt/php85/usr/bin/php
$PHP bin/console app:install --admin-email=vous@domaine.com
$PHP bin/console doctrine:schema:validate
$PHP bin/console app:launch:check
```

`doctrine:schema:validate` confirme que la base MariaDB d'Hostinger correspond bien au modèle.

### 3.4 Tâches planifiées (hPanel › Avancé › Tâches Cron)

| Quoi | Commande | Fréquence |
|---|---|---|
| Rappels du jour | `cd /home/u340339661/kiini && /opt/alt/php85/usr/bin/php bin/console app:reminders:send` | Chaque jour à 8 h (vérifier le fuseau horaire affiché par hPanel) |
| Sauvegarde supplémentaire (30 jours) | `cd /home/u340339661/kiini && PATH=/opt/alt/php85/usr/bin:$PATH bin/ops/sauvegarde.sh /home/u340339661/sauvegardes` | Chaque nuit à 3 h |

Hostinger fait déjà une sauvegarde quotidienne (hPanel › Fichiers › Sauvegardes). Télécharger aussi
régulièrement une copie sur votre ordinateur : une sauvegarde chez le même hébergeur ne protège pas de tout.

### 3.5 Mettre à jour une version en ligne

```bash
git commit -am "…"
bin/ops/envoyer-hostinger.sh
```

Les migrations s'appliquent toutes seules. Avant une mise à jour qui contient des migrations, lancer
d'abord la sauvegarde (`bin/ops/sauvegarde.sh ~/sauvegardes` sur le serveur).

### 3.6 Si quelque chose ne va pas

- **Page blanche ou erreur 500** : hPanel › Avancé › Journaux d'erreurs, ou `tail ~/kiini/var/log/*`.
- **Formulaires refusés après connexion** (HTTPS mal reconnu) : mettre `TRUSTED_PROXIES=REMOTE_ADDR` dans `.env.prod.local`, puis relancer `bin/ops/mise-a-jour.sh`.
- **Emails** : ils partent pendant la requête ; si le fournisseur est en panne, l'action échoue avec un message d'erreur. Vérifier `MAILER_DSN` et le tableau de bord du fournisseur.
- **Quand le trafic grandira** : passer à un VPS (sections 4 à 6) pour avoir un worker d'envoi permanent et plus de puissance.

## 4. VPS ou serveur dédié : déploiement manuel (première fois)

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

## 5. Processus permanents et tâches planifiées (VPS ou serveur dédié)

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

## 6. Mettre à jour une version déjà en ligne (VPS ou serveur dédié)

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
