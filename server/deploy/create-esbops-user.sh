#!/usr/bin/env bash
# Run ONCE on the Forge server as user `forge` (not root login).
# Creates a key-only operator account for workstation/Cursor deploy access.
#
#   scp server/deploy/create-esbops-user.sh forge@YOUR_SERVER:
#   ssh forge@YOUR_SERVER 'bash create-esbops-user.sh'
#
# Or paste/run while already SSH'd in as forge.

set -euo pipefail

OPS_USER="${OPS_USER:-esbops}"
PUB_KEY_FILE="${PUB_KEY_FILE:-}"

if [[ "$(whoami)" != "forge" ]]; then
  echo "Run this script as forge (you are: $(whoami))." >&2
  exit 1
fi

if ! sudo -n true 2>/dev/null; then
  echo "forge needs passwordless sudo (normal on Laravel Forge)." >&2
  exit 1
fi

if [[ -z "$PUB_KEY_FILE" ]]; then
  PUB_KEY='ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIG8rls7JzpHdSQrOCygsqkEi0N9YKkPI6G70wDACjpRy operator-band.edandtheshadowboys.com'
else
  PUB_KEY="$(cat "$PUB_KEY_FILE")"
fi

if id "$OPS_USER" &>/dev/null; then
  echo "User $OPS_USER already exists — updating SSH key only."
else
  sudo adduser --disabled-password --gecos "ESB portal operator" "$OPS_USER"
fi

sudo mkdir -p "/home/$OPS_USER/.ssh"
echo "$PUB_KEY" | sudo tee "/home/$OPS_USER/.ssh/authorized_keys" >/dev/null
sudo chmod 700 "/home/$OPS_USER/.ssh"
sudo chmod 600 "/home/$OPS_USER/.ssh/authorized_keys"
sudo chown -R "$OPS_USER:$OPS_USER" "/home/$OPS_USER/.ssh"

# Deploy only: run Forge's deploy script as forge (no root, no .env access required).
DEPLOY_GLOB='/home/forge/.forge/deploy*.sh'
sudo tee "/etc/sudoers.d/${OPS_USER}-deploy" >/dev/null <<EOF
${OPS_USER} ALL=(forge) NOPASSWD: ${DEPLOY_GLOB}
EOF
sudo chmod 440 "/etc/sudoers.d/${OPS_USER}-deploy"

echo ""
echo "Created/updated ${OPS_USER} (SSH key only, no password)."
echo "Test from your Mac:"
echo "  ssh -i server/deploy/keys/band-portal-operator ${OPS_USER}@134.199.173.4 whoami"
echo ""
echo "Deploy as ${OPS_USER}:"
echo "  ssh ${OPS_USER}@134.199.173.4 'sudo -u forge bash ${DEPLOY_GLOB}'"
