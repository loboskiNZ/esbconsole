#!/usr/bin/env bash
# Create a shared Chapter 1 invite link on the Forge server.
# Run as user `forge` (Forge → Commands, or SSH):
#
#   bash /home/forge/band.edandtheshadowboys.com/current/server/deploy/make-invite.sh
#
# Forge Commands must be a single line when using cd — use && between cd and php:
#
#   cd /home/forge/band.edandtheshadowboys.com/current/server && php artisan esb:make-invite 'Chapter 1 Test' --days=30

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

php artisan esb:make-invite "${1:-Chapter 1 Test}" --days="${2:-30}"
