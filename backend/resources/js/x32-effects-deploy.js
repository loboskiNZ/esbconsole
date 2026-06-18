function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function deployRoot(button) {
    return button.closest('[data-effect-deploy]');
}

function deployStatusElement(root) {
    return root?.querySelector('[data-effect-deploy-status]') ?? null;
}

function deployErrorElement(root) {
    return root?.querySelector('[data-effect-deploy-error]') ?? null;
}

function deployLabelForStatus(status) {
    switch (status) {
        case 'not_allocated':
            return 'Not allocated';
        case 'ready':
            return 'Ready to deploy';
        case 'deploying':
            return 'Deploying';
        case 'deployed':
            return 'Deployed';
        case 'failed':
            return 'Failed';
        default:
            return 'Ready to deploy';
    }
}

function setDeployStatus(root, status, message = '') {
    const statusEl = deployStatusElement(root);
    const errorEl = deployErrorElement(root);

    if (statusEl) {
        statusEl.textContent = deployLabelForStatus(status);
        statusEl.className = `vx32-effects-workspace__effect-deploy-status vx32-effects-workspace__effect-deploy-status--${status}`;
    }

    if (errorEl) {
        if (message) {
            errorEl.textContent = message;
            errorEl.hidden = false;
        } else {
            errorEl.textContent = '';
            errorEl.hidden = true;
        }
    }

    root.dataset.deployStatus = status;
}

function refreshDeployButtonState(root) {
    const button = root.querySelector('[data-effect-deploy-button]');
    const slotSelect = root.closest('[data-effect-package-item-card]')?.querySelector('[data-effect-slot-input]');
    const hasSlot = (slotSelect?.value ?? '') !== '';
    const liveAvailable = root.dataset.liveControl === 'true';

    root.dataset.hasSlot = hasSlot ? 'true' : 'false';

    if (button) {
        button.disabled = !hasSlot || !liveAvailable || root.dataset.deployStatus === 'deploying';
    }

    if (root.dataset.deployStatus === 'deploying' || root.dataset.deployStatus === 'deployed' || root.dataset.deployStatus === 'failed') {
        return;
    }

    setDeployStatus(root, hasSlot ? 'ready' : 'not_allocated');
}

export function refreshEffectDeployStateForCard(card) {
    const root = card?.querySelector('[data-effect-deploy]');

    if (root) {
        refreshDeployButtonState(root);
    }
}

async function deployEffect(button) {
    const root = deployRoot(button);
    const url = root?.dataset.deployUrl;

    if (!root || !url || button.disabled) {
        return;
    }

    setDeployStatus(root, 'deploying');
    button.disabled = true;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({}),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok || payload?.success !== true) {
            throw new Error(payload?.error ?? 'Effect deploy failed.');
        }

        setDeployStatus(root, 'deployed');
    } catch (error) {
        const message = error instanceof Error ? error.message : 'Effect deploy failed.';
        setDeployStatus(root, 'failed', message);
    } finally {
        refreshDeployButtonState(root);
    }
}

function initEffectsDeploy() {
    document.querySelectorAll('[data-effect-deploy-button]').forEach((button) => {
        button.addEventListener('click', () => deployEffect(button));
    });

    document.querySelectorAll('[data-effect-deploy]').forEach((root) => {
        refreshDeployButtonState(root);
    });
}

document.addEventListener('DOMContentLoaded', initEffectsDeploy);
