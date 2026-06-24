#!/usr/bin/env bash
# Trigger a Forge zero-downtime deploy for band.edandtheshadowboys.com.
#
# Usage:
#   ./server/deploy/remote-deploy.sh           # deploy only
#   ./server/deploy/remote-deploy.sh --push    # git push origin main, then deploy
#   ./server/deploy/remote-deploy.sh --verify 8750521
#
# Requires FORGE_DEPLOY_HOOK_URL in server/deploy/.env (Forge → Site → Deployments → Deploy hook).
#
# If deploy hook keeps cloning bbos-website, fix Forge Git repository first — see
# server/deploy/FORGE_BAND_SITE_REPO.md

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEPLOY_ENV="$ROOT/server/deploy/.env"
VERIFY_COMMIT="${1:-8750521}"

if [[ "${1:-}" == "--push" ]]; then
  git -C "$ROOT" push origin main
  shift
fi

if [[ "${1:-}" == "--verify" ]]; then
  VERIFY_COMMIT="${2:-8750521}"
  exec "$ROOT/server/deploy/verify-active-release.sh" "$VERIFY_COMMIT"
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
echo "Site must use Git repository loboskiNZ/esbconsole (not bbos-website)."
curl -fsS -X POST "$FORGE_DEPLOY_HOOK_URL"
echo
echo "Deploy hook accepted. Waiting for activation…"

for attempt in 1 2 3 4 5 6 7 8 9 10; do
  sleep 15
  if "$ROOT/server/deploy/verify-active-release.sh" "$VERIFY_COMMIT" 2>/dev/null; then
    echo "Active release verified."
    exit 0
  fi
  echo "Attempt ${attempt}/10: active release not yet verified…"
done

echo "Deploy hook ran but active release verification failed." >&2
echo "Check Forge deploy log. Common cause: site Git repo set to loboskiNZ/bbos-website." >&2
echo "See server/deploy/FORGE_BAND_SITE_REPO.md" >&2
exit 1
