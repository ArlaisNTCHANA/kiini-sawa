#!/bin/bash
# Tâche planifiée quotidienne (hPanel › Avancé › Tâches Cron) : rappels du jour.
# Commande à planifier : bash /home/UTILISATEUR/kiini/bin/ops/cron-rappels.sh
cd "$(dirname "$0")/../.." || exit 1
PHP=/opt/alt/php85/usr/bin/php; [ -x "$PHP" ] || PHP=php
mkdir -p var/log
echo "== $(date '+%Y-%m-%d %H:%M')" >> var/log/rappels.log
APP_ENV=prod "$PHP" -d disable_functions= bin/console app:reminders:send --no-interaction >> var/log/rappels.log 2>&1
# Annonces de l'équipe dont l'envoi a été interrompu (Admin › Écrire aux membres)
APP_ENV=prod "$PHP" -d disable_functions= bin/console app:annonces:envoyer --no-interaction >> var/log/rappels.log 2>&1
