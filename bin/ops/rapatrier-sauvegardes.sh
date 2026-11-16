#!/usr/bin/env bash
# À lancer DEPUIS VOTRE ORDINATEUR (dans web/) : copie les sauvegardes du serveur dans ~/KIINI-SAWA-sauvegardes.
# Une sauvegarde chez le même hébergeur ne protège pas de tout : gardez-en une copie chez vous (et idéalement ailleurs).
set -euo pipefail
cd "$(dirname "$0")/../.."
source bin/ops/hostinger.local
DEST="${1:-$HOME/KIINI-SAWA-sauvegardes}"
mkdir -p "$DEST"
rsync -az -e "ssh -p ${HOSTINGER_PORT:-65002}" "$HOSTINGER_SSH:sauvegardes/" "$DEST/"
echo "Sauvegardes copiées dans $DEST :"
ls -1t "$DEST" | head -5
