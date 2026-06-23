#!/usr/bin/env bash
# Band Portal — Laravel Forge deploy script (monorepo: Laravel app in /server)
# Adapt your existing Forge script; do not remove $CREATE_RELEASE / $ACTIVATE_RELEASE.

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# Forge links .env and storage at release root; Laravel lives in server/
ln -nfs $FORGE_SITE_PATH/.env $FORGE_RELEASE_DIRECTORY/server/.env
rm -rf $FORGE_RELEASE_DIRECTORY/server/storage
ln -nfs $FORGE_SITE_PATH/storage $FORGE_RELEASE_DIRECTORY/server/storage

# Root composer.json shim runs server/ install via post-install-cmd
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

cd $FORGE_RELEASE_DIRECTORY/server

npm ci || npm install
npm run build

$FORGE_PHP artisan optimize
$FORGE_PHP artisan storage:link
$FORGE_PHP artisan migrate --force

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
