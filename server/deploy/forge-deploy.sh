#!/usr/bin/env bash
# Laravel Forge deploy script for Band Portal (monorepo: composer.json in /server)
# Paste into Forge → site → Deployments, or source from a custom hook.

set -e

cd "$FORGE_SITE_PATH/server"

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
fi
