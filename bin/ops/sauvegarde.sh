#!/usr/bin/env bash
# Sauvegarde quotidienne de la base KIINI SAWA (à planifier, ex. chaque nuit à 3 h).
# Usage : DATABASE_URL="mysql://user:motdepasse@hote:3306/base" bin/ops/sauvegarde.sh /chemin/des/sauvegardes
# Garde 30 jours de sauvegardes compressées. Copiez aussi ce dossier hors du serveur (stockage objet, autre machine).
set -euo pipefail

DEST="${1:-./sauvegardes}"
mkdir -p "$DEST"

# Décompose DATABASE_URL (mysql://utilisateur:motdepasse@hote:port/base?options)
# Sur l'hébergement, l'adresse est lue dans la configuration compilée (.env.local.php).
if [[ -z "${DATABASE_URL:-}" && -f "$(dirname "$0")/../../.env.local.php" ]]; then
  DATABASE_URL="$(php -r 'echo (require $argv[1])["DATABASE_URL"] ?? "";' "$(dirname "$0")/../../.env.local.php")"
fi
url="${DATABASE_URL:?DATABASE_URL manquante}"
proto_removed="${url#*://}"
creds="${proto_removed%%@*}"
hostpart="${proto_removed#*@}"
user="${creds%%:*}"
pass="${creds#*:}"
hostport="${hostpart%%/*}"
host="${hostport%%:*}"
port="${hostport#*:}"; [ "$port" = "$hostport" ] && port=3306
db="${hostpart#*/}"; db="${db%%\?*}"

file="$DEST/kiini-sawa-$(date +%Y-%m-%d_%H%M).sql.gz"
MYSQL_PWD="$pass" mysqldump --single-transaction --quick --routines -h "$host" -P "$port" -u "$user" "$db" | gzip > "$file"
find "$DEST" -name 'kiini-sawa-*.sql.gz' -mtime +30 -delete
echo "Sauvegarde : $file"
