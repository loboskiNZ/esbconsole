#!/usr/bin/env bash
# Rsync chart PDFs directly into Forge shared library storage (forge SSH required).
#
# Usage:
#   ./server/deploy/rsync-library-charts.sh
#
# Alternative when only esb-band-ops SSH is available:
#   ./server/deploy/push-library-charts.sh --deploy

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SOURCE="${CHART_SYNC_SOURCE:-$ROOT/backend/storage/app/private/charts}"
REMOTE_HOST="${FORGE_RSYNC_HOST:-forge@134.199.173.4}"
REMOTE_TARGET="/home/forge/band.edandtheshadowboys.com/storage/app/library/charts/"

if [[ ! -d "$SOURCE" ]]; then
  echo "Chart source not found: $SOURCE" >&2
  exit 1
fi

echo "Syncing charts to ${REMOTE_HOST}:${REMOTE_TARGET}"
rsync -avz --delete "${SOURCE}/" "${REMOTE_HOST}:${REMOTE_TARGET}"

echo "Done. Set PORTAL_LIBRARY_STORAGE_ROOT on Forge if not already configured, then:"
echo "  ssh ${REMOTE_HOST} 'cd /home/forge/band.edandtheshadowboys.com/current/server && php artisan config:cache'"
