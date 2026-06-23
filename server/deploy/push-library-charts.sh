#!/usr/bin/env bash
# Upload chart PDFs to Forge shared library incoming/, then promote via deploy.
#
# Usage:
#   ./server/deploy/push-library-charts.sh              # upload tarball only
#   ./server/deploy/push-library-charts.sh --deploy     # upload then trigger Forge deploy
#
# Source defaults to backend/storage/app/private/charts (Director import output).
# Requires SSH host esb-band-ops (see server/deploy/ssh-config.snippet).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEPLOY_ENV="$ROOT/server/deploy/.env"
SOURCE="${CHART_SYNC_SOURCE:-$ROOT/backend/storage/app/private/charts}"
REMOTE_HOST="${FORGE_SSH_HOST:-esb-band-ops}"
REMOTE_INCOMING="/home/forge/band.edandtheshadowboys.com/storage/app/library/incoming"
REMOTE_TAR="$REMOTE_INCOMING/charts.tar.gz"

if [[ ! -d "$SOURCE" ]]; then
  echo "Chart source not found: $SOURCE" >&2
  exit 1
fi

echo "Uploading chart files …"
ssh "$REMOTE_HOST" "mkdir -p '$REMOTE_INCOMING' '/home/forge/band.edandtheshadowboys.com/storage/app/library/charts'"
rsync -avz "${SOURCE}/" "${REMOTE_HOST}:/home/forge/band.edandtheshadowboys.com/storage/app/library/charts/"

echo "Packaging charts tarball for deploy promotion …"
TMP_TAR="$(mktemp /tmp/esb-charts.XXXXXX.tar.gz)"
tar -czf "$TMP_TAR" -C "$(dirname "$SOURCE")" "$(basename "$SOURCE")"

echo "Uploading tarball to ${REMOTE_HOST}:${REMOTE_TAR} …"
scp "$TMP_TAR" "${REMOTE_HOST}:${REMOTE_TAR}"
rm -f "$TMP_TAR"

echo "Upload complete."
echo "Run Forge deploy to extract into shared library storage:"
echo "  ./server/deploy/remote-deploy.sh"

if [[ "${1:-}" == "--deploy" ]]; then
  if [[ ! -f "$DEPLOY_ENV" ]]; then
    echo "Missing $DEPLOY_ENV — cannot trigger deploy hook." >&2
    exit 1
  fi
  # shellcheck disable=SC1090
  source "$DEPLOY_ENV"
  if [[ -z "${FORGE_DEPLOY_HOOK_URL:-}" ]]; then
    echo "FORGE_DEPLOY_HOOK_URL is not set in $DEPLOY_ENV" >&2
    exit 1
  fi
  echo "Triggering Forge deploy to promote chart files …"
  curl -fsS -X POST "$FORGE_DEPLOY_HOOK_URL"
  echo
  echo "Deploy hook accepted."
fi
