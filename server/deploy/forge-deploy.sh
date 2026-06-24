#!/usr/bin/env bash
# Band Portal — Laravel Forge deploy script (monorepo: Laravel app in /server)

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

if [[ ! -f "$FORGE_RELEASE_DIRECTORY/server/artisan" || ! -f "$FORGE_RELEASE_DIRECTORY/server/composer.json" ]]; then
  echo "ERROR: Band Portal requires the esbconsole monorepo (server/artisan missing in release)." >&2
  echo "Forge → Sites → band.edandtheshadowboys.com → Meta → Repository must be loboskiNZ/esbconsole on branch main." >&2
  echo "This deploy cloned a non-monorepo repository (often loboskiNZ/bbos-website / Statamic)." >&2
  exit 1
fi

if [[ -f "$FORGE_RELEASE_DIRECTORY/composer.json" ]] && grep -q '"name": "statamic/statamic"' "$FORGE_RELEASE_DIRECTORY/composer.json" 2>/dev/null; then
  echo "ERROR: Release is Statamic (bbos-website), not esbconsole." >&2
  echo "Fix Forge site Git repository to loboskiNZ/esbconsole before redeploying." >&2
  exit 1
fi

export FORGE_SITE_PATH FORGE_RELEASE_DIRECTORY FORGE_PHP FORGE_COMPOSER

bash "$FORGE_RELEASE_DIRECTORY/server/deploy/esbconsole-release-build.sh"

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
