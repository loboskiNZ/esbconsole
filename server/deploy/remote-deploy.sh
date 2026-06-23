#!/usr/bin/env bash
# Trigger a Forge zero-downtime deploy for band.edandtheshadowboys.com.
#
# Usage:
#   ./server/deploy/remote-deploy.sh           # deploy only
#   ./server/deploy/remote-deploy.sh --push    # git push origin main, then deploy
#
# Requires FORGE_DEPLOY_HOOK_URL in server/deploy/.env (Forge → Site → Deployments → Deploy hook).

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEPLOY_ENV="$ROOT/server/deploy/.env"

if [[ "${1:-}" == "--push" ]]; then
  git -C "$ROOT" push origin main
  shift
fi

if [[ ! -f "$DEPLOY_ENV" ]]; then
  echo "Missing $DEPLOY_ENV" >&2
  echo "Copy server/deploy/.env.example → server/deploy/.env and set FORGE_DEPLOY_HOOK_URL." >&2
  exit 1
fi

# shellcheck disable=SC1090
source "$DEPLOY_ENV"

if [[ -z "${FORGE_DEPLOY_HOOK_URL:-}" ]]; then
  echo "FORGE_DEPLOY_HOOK_URL is not set in $DEPLOY_ENV" >&2
  exit 1
fi

echo "Deploying band.edandtheshadowboys.com via Forge deploy hook…"
curl -fsS -X POST "$FORGE_DEPLOY_HOOK_URL"
echo
echo "Deploy hook accepted."
