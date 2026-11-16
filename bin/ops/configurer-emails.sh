#!/usr/bin/env bash
# À lancer SUR LE SERVEUR, à la racine du projet : enregistre l'accès SMTP de Brevo dans .env.prod.local
# (clé saisie sans affichage, encodée pour l'URL), recompile la configuration et envoie un email de test.
# Usage : bash bin/ops/configurer-emails.sh IDENTIFIANT_SMTP EMAIL_DE_TEST
#   IDENTIFIANT_SMTP : Brevo › SMTP et API › onglet SMTP, « Identifiant » (ex. 9a1b2c001@smtp-brevo.com)
set -euo pipefail
cd "$(dirname "$0")/../.."

LOGIN="${1:?Identifiant SMTP manquant}"
TEST_TO="${2:?Adresse de test manquante}"
PHP="${PHP:-php}"
run() { "$PHP" -d disable_functions= "$@"; }
[[ -f .env.prod.local ]] || { echo "Fichier .env.prod.local manquant." >&2; exit 1; }

read -r -s -p "Clé SMTP Brevo (rien ne s'affiche, c'est normal) : " KEY
echo
[[ -n "$KEY" ]] || { echo "Clé vide." >&2; exit 1; }

DSN=$(KS_L="$LOGIN" KS_K="$KEY" "$PHP" -r 'printf("smtp://%s:%s@smtp-relay.brevo.com:587", rawurlencode(getenv("KS_L")), rawurlencode(getenv("KS_K")));')
unset KEY

tmp=$(mktemp)
grep -v '^MAILER_DSN=' .env.prod.local > "$tmp" || true
printf 'MAILER_DSN="%s"\n' "$DSN" >> "$tmp"
mv "$tmp" .env.prod.local
chmod 600 .env.prod.local

export APP_ENV=prod
run "$(command -v composer)" dump-env prod >/dev/null
run bin/console cache:clear >/dev/null 2>&1

FROM=$(grep '^MAILER_FROM=' .env.prod.local | cut -d= -f2- | tr -d '"')
echo "Envoi d'un email de test à $TEST_TO (expéditeur : $FROM)…"
if run bin/console mailer:test "$TEST_TO" --from="$FROM" --subject="KIINI SAWA : test d'envoi" --body="Si vous lisez ce message, les emails de KIINI SAWA fonctionnent." 2>&1 | grep -v -i deprecat | tail -3; then
  echo "Terminé. Vérifiez la boîte de réception (et les spams) de $TEST_TO."
fi
