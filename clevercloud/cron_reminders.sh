#!/bin/bash -l
# Rappels quotidiens (revues, rituels, échéances, fin d'essai et d'abonnement).
# Le cron tourne sur chaque instance : seule la première envoie, pour éviter les doublons.
if [[ "${INSTANCE_NUMBER:-0}" != "0" ]]; then
  exit 0
fi
cd "${APP_HOME}" || exit 1
php bin/console app:reminders:send --no-interaction
