#!/bin/bash
# Clever Cloud : exécuté après « composer install » (qui tourne avec --no-scripts).
# Compile les assets, prépare le cache et applique les migrations de la base.
set -euo pipefail

php bin/console importmap:install
php bin/console assets:install public
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
php bin/console cache:warmup
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
