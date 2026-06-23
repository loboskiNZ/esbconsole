#!/usr/bin/env bash
# Band Portal — Laravel Forge deploy script (monorepo: Laravel app in /server)

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Shared storage must exist before config:cache (view.compiled path)
mkdir -p $FORGE_SITE_PATH/storage/framework/{cache/data,sessions,views,testing}
mkdir -p $FORGE_SITE_PATH/storage/{app/public,logs}

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
