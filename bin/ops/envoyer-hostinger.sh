#!/usr/bin/env bash
# À lancer DEPUIS VOTRE ORDINATEUR, dans le dossier web/ : envoie le code sur Hostinger puis lance
# bin/ops/mise-a-jour.sh sur le serveur.
#
# Réglages dans bin/ops/hostinger.local (non versionné), d'après hPanel › Avancé › Accès SSH :
#   HOSTINGER_SSH="u123456789@123.45.67.89"
#   HOSTINGER_PORT=65002
#   HOSTINGER_DIR="domains/kiinisawa.com/kiini"
#   HOSTINGER_PHP=""          # facultatif, ex. /opt/alt/php84/usr/bin/php
set -euo pipefail
cd "$(dirname "$0")/../.."

conf="bin/ops/hostinger.local"
[[ -f "$conf" ]] || { echo "Créez $conf (voir l'en-tête de ce script)." >&2; exit 1; }
# shellcheck disable=SC1090
source "$conf"
: "${HOSTINGER_SSH:?}" "${HOSTINGER_DIR:?}"
HOSTINGER_PORT="${HOSTINGER_PORT:-65002}"

if [[ -n "$(git status --porcelain 2>/dev/null)" ]]; then
  echo "Attention : des modifications ne sont pas commitées. Elles seront envoyées quand même." >&2
fi

rsync -az --delete -e "ssh -p $HOSTINGER_PORT" \
  --exclude='/.git' --exclude='/vendor' --exclude='/var' --exclude='/node_modules' \
  --exclude='/public/assets' --exclude='/public/bundles' --exclude='/assets/vendor' \
  --exclude='/.env.local' --exclude='/.env.local.php' --exclude='/.env.*.local' \
  --exclude='/bin/ops/hostinger.local' --exclude='/tests' --exclude='/.phpunit.cache' \
  ./ "$HOSTINGER_SSH:$HOSTINGER_DIR/"

ssh -p "$HOSTINGER_PORT" "$HOSTINGER_SSH" "cd $HOSTINGER_DIR && PHP=${HOSTINGER_PHP:-php} bash bin/ops/mise-a-jour.sh"
echo "En ligne. Contrôle : ssh -p $HOSTINGER_PORT $HOSTINGER_SSH \"cd $HOSTINGER_DIR && php bin/console app:launch:check\""
