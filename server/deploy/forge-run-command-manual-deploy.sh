#!/usr/bin/env bash
# Paste into Forge → Sites → band.edandtheshadowboys.com → Commands → Run (runs as forge).
#
# Use when deploy hook clones the wrong repository (bbos-website) or current is stuck.
# Requires Forge site Git repository = loboskiNZ/esbconsole branch main (fix in Forge Meta first).
#
# After success:
#   ./server/deploy/verify-active-release.sh 8750521

set -euo pipefail

SITE="/home/forge/band.edandtheshadowboys.com"
RELEASE_ID="$(date +%s)"
RELEASE="${SITE}/releases/${RELEASE_ID}"
FORGE_SITE_PATH="$SITE"
FORGE_RELEASE_DIRECTORY="$RELEASE"
FORGE_PHP="$(command -v php8.4 || command -v php)"
FORGE_COMPOSER="$(command -v composer)"
export FORGE_SITE_PATH FORGE_RELEASE_DIRECTORY FORGE_PHP FORGE_COMPOSER

echo "Cloning esbconsole main into release ${RELEASE_ID}..."
git clone --depth 1 --branch main git@github.com:loboskiNZ/esbconsole.git "$RELEASE"

if [[ ! -f "$RELEASE/server/artisan" ]]; then
  echo "ERROR: clone is not esbconsole monorepo (server/artisan missing)." >&2
  exit 1
fi

BUILD="$RELEASE/server/deploy/esbconsole-release-build.sh"
if [[ ! -x "$BUILD" ]]; then
  chmod +x "$BUILD" 2>/dev/null || true
fi
bash "$BUILD"

echo "Activating release ${RELEASE_ID}..."
ln -sfn "$RELEASE" "${SITE}/current"

( flock -w 10 9 || exit 1
  echo "Reloading PHP-FPM..."
  sudo service "php8.4-fpm" reload
) 9>/tmp/esb-band-portal-fpm.lock

echo "Deployment complete. Active: $(readlink -f "${SITE}/current")"
