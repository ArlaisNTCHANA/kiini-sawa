#!/usr/bin/env bash
# À lancer SUR LE SERVEUR, à la racine du projet, après chaque envoi de code (fait automatiquement
# par bin/ops/envoyer-hostinger.sh). Installe les dépendances, compile les assets, applique les
# migrations et vide le cache.
# Variables facultatives : PHP=/chemin/vers/php  COMPOSER=/chemin/vers/composer
set -euo pipefail
cd "$(dirname "$0")/../.."

PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"
export APP_ENV=prod

# Les hébergeurs mutualisés désactivent proc_open, exec… (Hostinger : disable_functions). Les commandes
# d'installation en ont besoin (ex. Tailwind) : on les réautorise pour ces commandes seulement, en ligne de
# commande. Le site lui-même garde les restrictions de l'hébergeur.
run() { "$PHP" -d disable_functions= -d memory_limit=1G "$@"; }

if [[ ! -f .env.prod.local ]]; then
  echo "Fichier .env.prod.local manquant : copiez bin/ops/env.prod.local.exemple et remplissez-le." >&2
  exit 1
fi
if ! grep -q '^DATABASE_URL=' .env.prod.local; then
  echo "DATABASE_URL absente de .env.prod.local : lancez bin/ops/configurer-base.sh." >&2
  exit 1
fi
if ! "$PHP" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'; then
  echo "PHP 8.4 ou plus est requis en ligne de commande ($("$PHP" -r 'echo PHP_VERSION;') trouvé)." >&2
  echo "Relancez avec PHP=/opt/alt/php84/usr/bin/php (ou la version choisie dans hPanel)." >&2
  exit 1
fi

run "$(command -v "$COMPOSER")" install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
run "$(command -v "$COMPOSER")" dump-env prod
run bin/console importmap:install
run bin/console assets:install public
run bin/console tailwind:build --minify
run bin/console asset-map:compile
run bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
run bin/console cache:clear
run bin/console cache:warmup
echo "Mise à jour terminée."
