#!/usr/bin/env bash
# À lancer SUR LE SERVEUR, à la racine du projet : demande le mot de passe de la base (sans l'afficher),
# vérifie la connexion, détecte la version du serveur et écrit DATABASE_URL dans .env.prod.local.
# Usage : bash bin/ops/configurer-base.sh NOM_DE_LA_BASE UTILISATEUR [HOTE]
set -euo pipefail
cd "$(dirname "$0")/../.."

DB="${1:?Nom de la base manquant}"
USER_DB="${2:?Utilisateur manquant}"
HOST="${3:-localhost}"
PHP="${PHP:-php}"
[[ -f .env.prod.local ]] || { echo "Fichier .env.prod.local manquant." >&2; exit 1; }

read -r -s -p "Mot de passe de la base $DB (rien ne s'affiche, c'est normal) : " PASS
echo
[[ -n "$PASS" ]] || { echo "Mot de passe vide." >&2; exit 1; }

if ! VERSION=$(MYSQL_PWD="$PASS" mysql -h "$HOST" -u "$USER_DB" -N -B -e 'SELECT VERSION()' "$DB" 2>/dev/null); then
  echo "Connexion refusée : vérifiez le nom de la base, l'utilisateur et le mot de passe dans hPanel." >&2
  exit 1
fi

# Version au format attendu par Doctrine : « 11.8.2-MariaDB » ou « 8.4.0 »
NUM=$(echo "$VERSION" | grep -oE '^[0-9]+\.[0-9]+\.[0-9]+')
if echo "$VERSION" | grep -qi mariadb; then SERVER_VERSION="$NUM-MariaDB"; else SERVER_VERSION="$NUM"; fi

# Le mot de passe est encodé pour l'URL (caractères comme @ : / $ =) et n'apparaît jamais à l'écran.
URL=$(KS_U="$USER_DB" KS_P="$PASS" KS_H="$HOST" KS_D="$DB" KS_V="$SERVER_VERSION" "$PHP" -r '
  printf("mysql://%s:%s@%s:3306/%s?serverVersion=%s&charset=utf8mb4",
    rawurlencode(getenv("KS_U")), rawurlencode(getenv("KS_P")), getenv("KS_H"), rawurlencode(getenv("KS_D")), getenv("KS_V"));')
unset PASS

tmp=$(mktemp)
grep -v '^DATABASE_URL=' .env.prod.local > "$tmp" || true
printf 'DATABASE_URL="%s"\n' "$URL" >> "$tmp"
mv "$tmp" .env.prod.local
chmod 600 .env.prod.local
echo "Base configurée (serveur $VERSION). Vous pouvez fermer cette fenêtre."
