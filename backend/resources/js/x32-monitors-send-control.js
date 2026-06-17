import { dbToLinear, formatDb, linearMarkPercent, linearToDb, quantizeLinear } from './x32-fader-scale';
import {
    applyChannelMuteVisual,
    commitChannelMute,
    getSendControlRoot,
    isStripMonitorMuted,
    sendControlConfig,
    showStripSendError,
    writeMonitorSend,
} from './x32-monitors-send-api';

export {
    applyChannelMuteVisual,
    commitChannelMute,
    getSendControlRoot,
    isStripMonitorMuted,
    sendControlConfig,
    writeMonitorSend,
};

const WRITE_THROTTLE_MS = 35;

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function readStripLevelDb(strip) {
    const fromData = parseFloat(strip.getAttribute('data-level-db') ?? '');

    if (Number.isFinite(fromData)) {
        return fromData;
    }

    const handle = strip.querySelector('[data-channel-fader-handle]');

    if (!handle) {
        return 0;
    }

    const pct = parseFloat(handle.style.bottom) || 50;

    return linearToDb(clamp(pct, 0, 100) / 100);
}

function applyStripLevel(strip, levelDb) {
    const linear = dbToLinear(levelDb);
    const pct = linearMarkPercent(linear);
    const handle = strip.querySelector('[data-channel-fader-handle]');
    const level = strip.querySelector('[data-channel-fader-level]');

    strip.setAttribute('data-level-db', levelDb.toFixed(2));
    strip.dataset.confirmedLevelDb = levelDb.toFixed(2);

    if (handle) {
        handle.style.bottom = `${pct}%`;
    }

    if (level) {
        level.textContent = formatDb(levelDb);
        level.removeAttribute('title');
    }

    strip.classList.remove('is-send-error');
    delete strip.dataset.sendError;
}

function revertStripLevel(strip) {
    const confirmed = parseFloat(strip.dataset.confirmedLevelDb ?? strip.getAttribute('data-level-db') ?? '');

    if (Number.isFinite(confirmed)) {
        applyStripLevel(strip, confirmed);
    }
}

class MonitorsSendControl {
    constructor(root) {
        this.root = root;
        this.pendingWrites = new Map();
        this.dragState = null;
        this.bind();
    }

    get available() {
        return sendControlConfig(this.root).available;
    }

    channelNumber(strip) {
        return parseInt(strip.getAttribute('data-channel') ?? '0', 10);
    }

    async writeSend(channel, parameter, value, { final = true } = {}) {
        if (!this.available || !final) {
            return null;
        }

        const writeKey = `${channel}:${parameter}`;

        if (this.pendingWrites.has(writeKey)) {
            clearTimeout(this.pendingWrites.get(writeKey));
        }

        return new Promise((resolve) => {
            const dispatch = async () => {
                this.pendingWrites.delete(writeKey);
                resolve(await writeMonitorSend(this.root, channel, parameter, value));
            };

            if (parameter === 'level' && !final) {
                this.pendingWrites.set(writeKey, setTimeout(dispatch, WRITE_THROTTLE_MS));

                return;
            }

            dispatch();
        });
    }

    async commitLevel(strip, linear) {
        const channel = this.channelNumber(strip);
        const quantized = quantizeLinear(linear);
        const payload = await this.writeSend(channel, 'level', quantized, { final: true });

        if (!payload?.success) {
            showStripSendError(strip, payload?.error ?? 'Monitor send level was not confirmed.');
            revertStripLevel(strip);

            return;
        }

        const confirmedLinear = typeof payload.confirmed_value === 'number'
            ? payload.confirmed_value
            : quantized;

        applyStripLevel(strip, linearToDb(confirmedLinear));
    }

    async commitMute(strip) {
        if (strip.dataset.mutePending === 'true') {
            return;
        }

        strip.dataset.mutePending = 'true';

        try {
            await commitChannelMute(this.root, strip);
        } finally {
            delete strip.dataset.mutePending;
        }
    }

    moveFader(strip, event) {
        const track = strip.querySelector('[data-channel-fader-track]');

        if (!track) {
            return;
        }

        const rect = track.getBoundingClientRect();

        if (rect.height <= 0) {
            return;
        }

        const ratio = clamp(1 - ((event.clientY - rect.top) / rect.height), 0, 1);
        const levelDb = linearToDb(quantizeLinear(ratio));

        applyStripLevel(strip, levelDb);
    }

    bindStrip(strip) {
        const levelEnabled = strip.dataset.sendLevelEnabled === 'true';
        const muteEnabled = strip.dataset.sendMuteEnabled === 'true';

        if (!levelEnabled && !muteEnabled) {
            return;
        }

        strip.classList.add('is-send-control-enabled');

        const initialLevelDb = readStripLevelDb(strip);

        if (Number.isFinite(initialLevelDb)) {
            strip.dataset.confirmedLevelDb = initialLevelDb.toFixed(2);
        }

        applyChannelMuteVisual(strip, isStripMonitorMuted(strip));

        const muteButton = strip.querySelector('[data-channel-mute]');

        if (muteEnabled && muteButton) {
            muteButton.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                this.commitMute(strip);
            });
        }

        const handle = strip.querySelector('[data-channel-fader-handle]');
        const track = strip.querySelector('[data-channel-fader-track]');

        if (!levelEnabled || !handle || !track) {
            return;
        }

        handle.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            event.stopPropagation();
            handle.setPointerCapture(event.pointerId);
            this.dragState = {
                pointerId: event.pointerId,
                strip,
                handle,
            };
            this.moveFader(strip, event);
        });
    }

    bind() {
        if (!this.available) {
            return;
        }

        for (const strip of this.root.querySelectorAll('[data-channel-strip]')) {
            this.bindStrip(strip);
        }

        document.addEventListener('pointermove', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            event.preventDefault();
            this.moveFader(this.dragState.strip, event);
        });

        document.addEventListener('pointerup', async (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            const { strip, handle } = this.dragState;
            this.dragState = null;

            handle.releasePointerCapture?.(event.pointerId);

            const levelDb = readStripLevelDb(strip);
            const linear = dbToLinear(levelDb);

            await this.commitLevel(strip, linear);
        });

        document.addEventListener('pointercancel', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            const { strip } = this.dragState;
            this.dragState = null;
            revertStripLevel(strip);
        });
    }
}

export function initMonitorsSendControls(root = document) {
    root.querySelectorAll('[data-monitors-send-control]').forEach((control) => {
        if (control.dataset.monitorsSendControlBound === 'true') {
            return;
        }

        control.dataset.monitorsSendControlBound = 'true';
        new MonitorsSendControl(control);
    });
}

function bootMonitorsSendControls() {
    initMonitorsSendControls();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootMonitorsSendControls);
} else {
    bootMonitorsSendControls();
}
