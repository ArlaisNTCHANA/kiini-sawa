#!/bin/bash
# Tâche planifiée nocturne (hPanel › Avancé › Tâches Cron) : sauvegarde de la base, 30 jours gardés dans ~/sauvegardes.
# Commande à planifier : bash /home/UTILISATEUR/kiini/bin/ops/cron-sauvegarde.sh
cd "$(dirname "$0")/../.." || exit 1
[ -x /opt/alt/php85/usr/bin/php ] && export PATH="/opt/alt/php85/usr/bin:$PATH"
mkdir -p var/log
bash bin/ops/sauvegarde.sh "$HOME/sauvegardes" >> var/log/sauvegarde.log 2>&1
