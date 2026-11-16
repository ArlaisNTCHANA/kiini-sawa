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

if [[ ! -f .env.prod.local ]]; then
  echo "Fichier .env.prod.local manquant : copiez bin/ops/env.prod.local.exemple et remplissez-le." >&2
  exit 1
fi
if ! "$PHP" -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'; then
  echo "PHP 8.4 ou plus est requis en ligne de commande ($("$PHP" -r 'echo PHP_VERSION;') trouvé)." >&2
  echo "Relancez avec PHP=/opt/alt/php84/usr/bin/php (ou la version choisie dans hPanel)." >&2
  exit 1
fi

"$PHP" "$(command -v "$COMPOSER")" install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
"$PHP" "$(command -v "$COMPOSER")" dump-env prod
"$PHP" bin/console importmap:install
"$PHP" bin/console assets:install public
"$PHP" bin/console tailwind:build --minify
"$PHP" bin/console asset-map:compile
"$PHP" bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
"$PHP" bin/console cache:clear
"$PHP" bin/console cache:warmup
echo "Mise à jour terminée."
