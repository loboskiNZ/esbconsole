function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

export function getSendControlRoot() {
    return document.querySelector('[data-monitors-send-control]');
}

export function sendControlConfig(root = getSendControlRoot()) {
    return {
        updateUrl: root?.dataset.sendControlUrl ?? '',
        available: root?.dataset.liveControl === 'true',
    };
}

export async function writeMonitorSend(root, channel, parameter, value) {
    const { updateUrl, available } = sendControlConfig(root);

    if (!available || !updateUrl) {
        return {
            success: false,
            error: 'Live monitor send control is not available.',
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
                channel,
                parameter,
                value,
            }),
        });

        const payload = await response.json();

        if (!response.ok) {
            return {
                success: false,
                error: payload?.message ?? payload?.error ?? `Monitor send write failed (${response.status}).`,
            };
        }

        return payload;
    } catch (error) {
        return {
            success: false,
            error: error instanceof Error ? error.message : 'Monitor send write failed.',
        };
    }
}

export function isStripMonitorMuted(strip) {
    return strip?.dataset.confirmedMuted === 'true'
        || strip?.querySelector('[data-channel-mute]')?.hasAttribute('data-muted') === true;
}

export function resolveMutedFromPayload(payload, fallbackMuted) {
    if (payload === null || typeof payload !== 'object') {
        return fallbackMuted;
    }

    const { confirmed_value: confirmedValue, requested_value: requestedValue } = payload;

    if (typeof confirmedValue === 'boolean') {
        return confirmedValue;
    }

    if (confirmedValue === 1 || confirmedValue === '1') {
        return true;
    }

    if (confirmedValue === 0 || confirmedValue === '0') {
        return false;
    }

    if (typeof requestedValue === 'boolean') {
        return requestedValue;
    }

    return fallbackMuted;
}

export function applyChannelMuteVisual(strip, muted) {
    const button = strip?.querySelector('[data-channel-mute]');

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
    strip.classList.remove('is-send-error');
    delete strip.dataset.sendError;
}

export function revertChannelMuteVisual(strip) {
    applyChannelMuteVisual(strip, strip.dataset.confirmedMuted === 'true');
}

export function showStripMuteError(strip, message) {
    const button = strip?.querySelector('[data-channel-mute]');

    strip.classList.add('is-send-error');
    strip.dataset.sendError = message;

    if (button) {
        button.title = message;
    }
}

export function showStripSendError(strip, message) {
    strip.classList.add('is-send-error');
    strip.dataset.sendError = message;

    const level = strip.querySelector('[data-channel-fader-level]');

    if (level) {
        level.textContent = 'ERR';
        level.title = message;
    }
}

export async function commitChannelMute(root, strip, targetMuted = null) {
    const button = strip.querySelector('[data-channel-mute]');

    if (!button || strip.dataset.sendMuteEnabled !== 'true') {
        return {
            success: false,
            error: 'Monitor mute is not available for this channel.',
        };
    }

    const channel = parseInt(strip.getAttribute('data-channel') ?? '0', 10);
    const previousMuted = strip.dataset.confirmedMuted === 'true';
    const muted = targetMuted ?? !previousMuted;

    applyChannelMuteVisual(strip, muted);

    const payload = await writeMonitorSend(root, channel, 'mute', muted);

    if (!payload?.success) {
        showStripMuteError(strip, payload?.error ?? 'Monitor send mute was not confirmed.');
        applyChannelMuteVisual(strip, previousMuted);

        return payload ?? { success: false, error: 'Monitor send mute was not confirmed.' };
    }

    const confirmedMuted = resolveMutedFromPayload(payload, muted);
    applyChannelMuteVisual(strip, confirmedMuted);

    return payload;
}
