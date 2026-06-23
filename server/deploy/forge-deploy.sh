#!/usr/bin/env bash
# Band Portal — Laravel Forge deploy script (monorepo: app in /server)
# Replace the ENTIRE deploy script in Forge → site → Deployments.

set -e

cd "$FORGE_RELEASE_PATH"

# Forge links .env and storage at release root; Laravel app is in server/
ln -nfs "$FORGE_SITE_PATH/.env" "$FORGE_RELEASE_PATH/server/.env"
rm -rf "$FORGE_RELEASE_PATH/server/storage"
ln -nfs "$FORGE_SITE_PATH/storage" "$FORGE_RELEASE_PATH/server/storage"

# Root composer.json delegates to server/ via post-install-cmd
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

cd "$FORGE_RELEASE_PATH/server"

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
fi
