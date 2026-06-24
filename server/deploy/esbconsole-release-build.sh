#!/usr/bin/env bash
# Build a Band Portal release directory (composer, npm, artisan) after clone.
# Used by forge-deploy.sh and forge-run-command-manual-deploy.sh.
#
# Required env:
#   FORGE_SITE_PATH       — e.g. /home/forge/band.edandtheshadowboys.com
#   FORGE_RELEASE_DIRECTORY — release root containing server/
#   FORGE_PHP, FORGE_COMPOSER — optional; default php8.4/php and composer

set -euo pipefail

FORGE_PHP="${FORGE_PHP:-$(command -v php8.4 || command -v php)}"
FORGE_COMPOSER="${FORGE_COMPOSER:-$(command -v composer)}"

if [[ -z "${FORGE_SITE_PATH:-}" || -z "${FORGE_RELEASE_DIRECTORY:-}" ]]; then
  echo "FORGE_SITE_PATH and FORGE_RELEASE_DIRECTORY must be set." >&2
  exit 1
fi

mkdir -p "$FORGE_SITE_PATH/storage/framework/{cache/data,sessions,views,testing}"
mkdir -p "$FORGE_SITE_PATH/storage/{app/public,logs}"

LIBRARY_STORAGE_ROOT="$FORGE_SITE_PATH/storage/app/library"
LIBRARY_INCOMING="$LIBRARY_STORAGE_ROOT/incoming"
mkdir -p "$LIBRARY_STORAGE_ROOT/charts" "$LIBRARY_INCOMING"
chmod 755 "$LIBRARY_STORAGE_ROOT" "$LIBRARY_STORAGE_ROOT/charts"
chmod 777 "$LIBRARY_INCOMING"

if [[ -f "$LIBRARY_INCOMING/charts.tar.gz" ]]; then
  tar -xzf "$LIBRARY_INCOMING/charts.tar.gz" -C "$LIBRARY_STORAGE_ROOT"
  rm -f "$LIBRARY_INCOMING/charts.tar.gz"
fi

if [[ -d "$LIBRARY_INCOMING/charts" ]]; then
  rsync -a "$LIBRARY_INCOMING/charts/" "$LIBRARY_STORAGE_ROOT/charts/"
  rm -rf "$LIBRARY_INCOMING/charts"
fi

if grep -qE '^PORTAL_LIBRARY_STORAGE_ROOT=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  sed -i "s|^PORTAL_LIBRARY_STORAGE_ROOT=.*|PORTAL_LIBRARY_STORAGE_ROOT=$LIBRARY_STORAGE_ROOT|" "$FORGE_SITE_PATH/.env"
else
  echo "PORTAL_LIBRARY_STORAGE_ROOT=$LIBRARY_STORAGE_ROOT" >> "$FORGE_SITE_PATH/.env"
fi

if ! grep -qE '^PORTAL_LIBRARY_CONNECTION=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  echo "PORTAL_LIBRARY_CONNECTION=library" >> "$FORGE_SITE_PATH/.env"
fi

if ! grep -qE '^PORTAL_LIBRARY_CHART_DISK=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  echo "PORTAL_LIBRARY_CHART_DISK=library" >> "$FORGE_SITE_PATH/.env"
fi

ln -nfs "$FORGE_SITE_PATH/.env" "$FORGE_RELEASE_DIRECTORY/server/.env"
rm -rf "$FORGE_RELEASE_DIRECTORY/server/storage"
ln -nfs "$FORGE_SITE_PATH/storage" "$FORGE_RELEASE_DIRECTORY/server/storage"

cd "$FORGE_RELEASE_DIRECTORY"
"$FORGE_COMPOSER" install --no-dev --no-interaction --prefer-dist --optimize-autoloader

cd "$FORGE_RELEASE_DIRECTORY/server"

if ! grep -qE '^DB_CONNECTION=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  echo "ERROR: DB_CONNECTION is not set in $FORGE_SITE_PATH/.env" >&2
  exit 1
fi

if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
  "$FORGE_PHP" artisan key:generate --force
fi

if "$FORGE_PHP" artisan list 2>/dev/null | grep -q 'cloud:stabilise'; then
  "$FORGE_PHP" artisan cloud:stabilise --mark-migrations --target=pgsql
fi

npm ci || npm install
npm run build

"$FORGE_PHP" artisan optimize
"$FORGE_PHP" artisan storage:link
"$FORGE_PHP" artisan migrate --force
"$FORGE_PHP" artisan studio:library-promote-incoming
"$FORGE_PHP" artisan studio:normalize-library-chart-permissions

if "$FORGE_PHP" artisan list 2>/dev/null | grep -q 'studio:verify-chart-file-access'; then
  "$FORGE_PHP" artisan studio:verify-chart-file-access 14 3 || true
fi
