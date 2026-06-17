function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

export function getBusMasterControlRoot() {
    return document.querySelector('[data-monitors-send-control]');
}

export function busMasterControlConfig(root = getBusMasterControlRoot()) {
    return {
        updateUrl: root?.dataset.busMasterControlUrl ?? '',
        available: root?.dataset.busMasterLiveControl === 'true',
        reason: root?.dataset.busMasterControlReason ?? '',
    };
}

export async function writeMonitorBusMaster(root, parameter, value) {
    const { updateUrl, available } = busMasterControlConfig(root);

    if (!available || !updateUrl) {
        return {
            success: false,
            error: 'Live monitor bus master control is not available.',
        };
    }

    try {
        const response = await fetch(updateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                parameter,
                value,
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            return {
                success: false,
                error: payload?.message ?? payload?.error ?? `Monitor bus master write failed (${response.status}).`,
            };
        }

        return payload;
    } catch (error) {
        return {
            success: false,
            error: error instanceof Error ? error.message : 'Monitor bus master write failed.',
        };
    }
}

export function isBusMasterMuted(strip) {
    return strip?.dataset.confirmedMuted === 'true'
        || strip?.querySelector('[data-bus-master-mute]')?.hasAttribute('data-muted') === true;
}

export function applyBusMasterMuteVisual(strip, muted) {
    const button = strip?.querySelector('[data-bus-master-mute]');

    if (!button) {
        return;
    }

    if (muted) {
        button.setAttribute('data-muted', '');
        button.classList.add('is-muted');
        button.setAttribute('aria-pressed', 'true');
    } else {
        button.removeAttribute('data-muted');
        button.classList.remove('is-muted');
        button.setAttribute('aria-pressed', 'false');
    }

    strip.dataset.confirmedMuted = muted ? 'true' : 'false';
    strip.classList.remove('is-bus-master-error');
    delete strip.dataset.busMasterError;
}

export function showBusMasterError(strip, message) {
    strip.classList.add('is-bus-master-error');
    strip.dataset.busMasterError = message;

    const level = strip.querySelector('[data-bus-master-fader-level]');

    if (level) {
        level.title = message;
    }
}

export async function commitBusMasterMute(root, strip, targetMuted = null) {
    const button = strip.querySelector('[data-bus-master-mute]');

    if (!button || strip.dataset.busMasterMuteEnabled !== 'true') {
        return {
            success: false,
            error: 'Monitor bus master mute is not available.',
        };
    }

    const previousMuted = isBusMasterMuted(strip);
    const muted = targetMuted ?? !previousMuted;

    applyBusMasterMuteVisual(strip, muted);

    const payload = await writeMonitorBusMaster(root, 'mute', muted);

    if (!payload?.success) {
        applyBusMasterMuteVisual(strip, previousMuted);
        showBusMasterError(strip, payload?.error ?? 'Monitor bus master mute was not confirmed.');

        return payload;
    }

    const confirmedMuted = payload.display_value === 'Muted'
        || payload.confirmed_value === 0
        || payload.confirmed_value === false;

    applyBusMasterMuteVisual(strip, confirmedMuted);

    return payload;
}
