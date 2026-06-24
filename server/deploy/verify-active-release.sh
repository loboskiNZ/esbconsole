#!/usr/bin/env bash
# Verify band.edandtheshadowboys.com active release is esbconsole monorepo with expected commit.
#
# Usage:
#   ./server/deploy/verify-active-release.sh
#   ./server/deploy/verify-active-release.sh 8750521

set -euo pipefail

EXPECTED_COMMIT="${1:-8750521}"
SSH_HOST="${FORGE_SSH_HOST:-esb-band-ops}"
SITE="/home/forge/band.edandtheshadowboys.com"
CURRENT="$SITE/current"

read -r -d '' REMOTE_SCRIPT <<'EOS' || true
set -euo pipefail
SITE="/home/forge/band.edandtheshadowboys.com"
CURRENT="$SITE/current"
EXPECTED="$1"

fail() { echo "VERIFY FAIL: $1" >&2; exit 1; }

[[ -L "$CURRENT" ]] || fail "current is not a symlink"
RELEASE="$(readlink -f "$CURRENT")"
echo "active_release=$(basename "$RELEASE")"
echo "release_path=$RELEASE"

[[ -f "$RELEASE/server/artisan" ]] || fail "server/artisan missing — not esbconsole monorepo layout"
[[ -f "$RELEASE/server/composer.json" ]] || fail "server/composer.json missing"

if [[ -f "$RELEASE/composer.json" ]] && grep -q '"name": "statamic/statamic"' "$RELEASE/composer.json" 2>/dev/null; then
  fail "active release is Statamic/bbos-website, not esbconsole"
fi

REQ="$RELEASE/server/app/Http/Requests/StoreOnboardingRequest.php"
REG="$RELEASE/server/app/Services/OnboardingRegistrationService.php"
[[ -f "$REQ" ]] || fail "StoreOnboardingRequest.php missing"
[[ -f "$REG" ]] || fail "OnboardingRegistrationService.php missing"

if grep -qE 'human_answer|human_check|OnboardingHumanCheck' "$REQ"; then
  fail "StoreOnboardingRequest still references human verification"
fi

if ! grep -q "public_id" "$REG"; then
  fail "OnboardingRegistrationService missing users.public_id on registration"
fi

if [[ -d "$RELEASE/.git" ]]; then
  git -C "$RELEASE" config --global --add safe.directory "$RELEASE" 2>/dev/null || true
  HASH="$(git -C "$RELEASE" rev-parse HEAD 2>/dev/null || echo unknown)"
  echo "git_head=$HASH"
  if [[ "$HASH" != unknown && "$HASH" != "$EXPECTED"* && "$EXPECTED" != "$HASH"* ]]; then
    fail "git HEAD $HASH does not match expected $EXPECTED"
  fi
else
  echo "git_head=unknown"
fi

echo "VERIFY OK"
EOS

OUTPUT="$(ssh "$SSH_HOST" "bash -s -- '$EXPECTED_COMMIT'" <<< "$REMOTE_SCRIPT")"
echo "$OUTPUT"

echo "$OUTPUT" | grep -q "VERIFY OK"
