#!/usr/bin/env bash
# Band Portal — Laravel Forge deploy script (monorepo: Laravel app in /server)

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Shared storage must exist before config:cache (view.compiled path)
mkdir -p $FORGE_SITE_PATH/storage/framework/{cache/data,sessions,views,testing}
mkdir -p $FORGE_SITE_PATH/storage/{app/public,logs}

# Studio chart library — private shared storage (outside public web root)
LIBRARY_STORAGE_ROOT="$FORGE_SITE_PATH/storage/app/library"
LIBRARY_INCOMING="$LIBRARY_STORAGE_ROOT/incoming"
mkdir -p "$LIBRARY_STORAGE_ROOT/charts" "$LIBRARY_INCOMING"
chmod 750 "$LIBRARY_STORAGE_ROOT"
chmod 750 "$LIBRARY_STORAGE_ROOT/charts"
chmod 777 "$LIBRARY_INCOMING"

if [ -f "$LIBRARY_INCOMING/charts.tar.gz" ]; then
  tar -xzf "$LIBRARY_INCOMING/charts.tar.gz" -C "$LIBRARY_STORAGE_ROOT"
  rm -f "$LIBRARY_INCOMING/charts.tar.gz"
fi

if [ -d "$LIBRARY_INCOMING/charts" ]; then
  rsync -a "$LIBRARY_INCOMING/charts/" "$LIBRARY_STORAGE_ROOT/charts/"
  rm -rf "$LIBRARY_INCOMING/charts"
fi

if ! grep -qE '^PORTAL_LIBRARY_STORAGE_ROOT=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  echo "PORTAL_LIBRARY_STORAGE_ROOT=$LIBRARY_STORAGE_ROOT" >> "$FORGE_SITE_PATH/.env"
fi

if ! grep -qE '^PORTAL_LIBRARY_CONNECTION=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  echo "PORTAL_LIBRARY_CONNECTION=library" >> "$FORGE_SITE_PATH/.env"
fi

if ! grep -qE '^PORTAL_LIBRARY_CHART_DISK=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  echo "PORTAL_LIBRARY_CHART_DISK=library" >> "$FORGE_SITE_PATH/.env"
fi

# Forge links .env and storage at release root; Laravel lives in server/
ln -nfs $FORGE_SITE_PATH/.env $FORGE_RELEASE_DIRECTORY/server/.env
rm -rf $FORGE_RELEASE_DIRECTORY/server/storage
ln -nfs $FORGE_SITE_PATH/storage $FORGE_RELEASE_DIRECTORY/server/storage

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

cd $FORGE_RELEASE_DIRECTORY/server

if ! grep -qE '^DB_CONNECTION=' "$FORGE_SITE_PATH/.env" 2>/dev/null; then
  echo "ERROR: DB_CONNECTION is not set in $FORGE_SITE_PATH/.env" >&2
  echo "Laravel will fall back to ephemeral SQLite inside each release (data lost on deploy)." >&2
  echo "Add PostgreSQL credentials to the Forge site Environment tab, then redeploy." >&2
  exit 1
fi

if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
  $FORGE_PHP artisan key:generate --force
fi

npm ci || npm install
npm run build

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
