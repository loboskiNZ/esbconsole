import { dbToLinear, formatDb, linearMarkPercent, linearToDb, quantizeLinear } from './x32-fader-scale';
import {
    applyBusMasterMuteVisual,
    busMasterControlConfig,
    commitBusMasterMute,
    getBusMasterControlRoot,
    isBusMasterMuted,
    showBusMasterError,
    writeMonitorBusMaster,
} from './x32-monitors-bus-master-api';

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function readBusMasterLevelDb(strip) {
    const fromData = parseFloat(strip.getAttribute('data-level-db') ?? '');

    if (Number.isFinite(fromData)) {
        return fromData;
    }

    const handle = strip.querySelector('[data-bus-master-fader-handle]');

    if (!handle) {
        return 0;
    }

    const pct = parseFloat(handle.style.bottom) || 50;

    return linearToDb(clamp(pct, 0, 100) / 100);
}

function applyBusMasterLevel(strip, levelDb) {
    const linear = dbToLinear(levelDb);
    const pct = linearMarkPercent(linear);
    const handle = strip.querySelector('[data-bus-master-fader-handle]');
    const level = strip.querySelector('[data-bus-master-fader-level]');

    strip.setAttribute('data-level-db', levelDb.toFixed(2));
    strip.dataset.confirmedLevelDb = levelDb.toFixed(2);

    if (handle) {
        handle.style.bottom = `${pct}%`;
    }

    if (level) {
        level.textContent = `${formatDb(levelDb)} dB`;
        level.removeAttribute('title');
    }

    strip.classList.remove('is-bus-master-error');
    delete strip.dataset.busMasterError;
}

function revertBusMasterLevel(strip) {
    const confirmed = parseFloat(strip.dataset.confirmedLevelDb ?? strip.getAttribute('data-level-db') ?? '');

    if (Number.isFinite(confirmed)) {
        applyBusMasterLevel(strip, confirmed);
    }
}

class MonitorsBusMasterControl {
    constructor(root) {
        this.root = root;
        this.strip = root.querySelector('[data-bus-master-strip]');
        this.dragState = null;
        this.bind();
    }

    get available() {
        return busMasterControlConfig(this.root).available;
    }

    moveFader(event) {
        const track = this.strip?.querySelector('[data-bus-master-fader-track]');

        if (!track) {
            return;
        }

        const rect = track.getBoundingClientRect();

        if (rect.height <= 0) {
            return;
        }

        const ratio = clamp(1 - ((event.clientY - rect.top) / rect.height), 0, 1);
        const levelDb = linearToDb(quantizeLinear(ratio));

        applyBusMasterLevel(this.strip, levelDb);
    }

    async commitLevel(linear) {
        const quantized = quantizeLinear(linear);
        const payload = await writeMonitorBusMaster(this.root, 'level', quantized);

        if (!payload?.success) {
            showBusMasterError(this.strip, payload?.error ?? 'Monitor bus master level was not confirmed.');
            revertBusMasterLevel(this.strip);

            return;
        }

        const confirmedLinear = typeof payload.confirmed_value === 'number'
            ? payload.confirmed_value
            : quantized;

        applyBusMasterLevel(this.strip, linearToDb(confirmedLinear));
    }

    bind() {
        if (!this.strip || !this.available) {
            return;
        }

        const levelEnabled = this.strip.dataset.busMasterLevelEnabled === 'true';
        const muteEnabled = this.strip.dataset.busMasterMuteEnabled === 'true';

        if (!levelEnabled && !muteEnabled) {
            return;
        }

        this.strip.classList.add('is-bus-master-control-enabled');

        const initialLevelDb = readBusMasterLevelDb(this.strip);

        if (Number.isFinite(initialLevelDb)) {
            this.strip.dataset.confirmedLevelDb = initialLevelDb.toFixed(2);
        }

        if (muteEnabled) {
            applyBusMasterMuteVisual(this.strip, isBusMasterMuted(this.strip));

            this.strip.querySelector('[data-bus-master-mute]')?.addEventListener('click', async (event) => {
                event.preventDefault();
                event.stopPropagation();

                if (this.strip.dataset.mutePending === 'true') {
                    return;
                }

                this.strip.dataset.mutePending = 'true';

                try {
                    await commitBusMasterMute(this.root, this.strip);
                } finally {
                    delete this.strip.dataset.mutePending;
                }
            });
        }

        const handle = this.strip.querySelector('[data-bus-master-fader-handle]');
        const track = this.strip.querySelector('[data-bus-master-fader-track]');

        if (!levelEnabled || !handle || !track) {
            return;
        }

        handle.addEventListener('pointerdown', (event) => {
            event.preventDefault();
            event.stopPropagation();
            handle.setPointerCapture(event.pointerId);
            this.dragState = {
                pointerId: event.pointerId,
                handle,
            };
            this.moveFader(event);
        });

        document.addEventListener('pointermove', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            event.preventDefault();
            this.moveFader(event);
        });

        document.addEventListener('pointerup', async (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            const { handle } = this.dragState;
            this.dragState = null;
            handle?.releasePointerCapture?.(event.pointerId);

            const levelDb = readBusMasterLevelDb(this.strip);
            const linear = dbToLinear(levelDb);

            await this.commitLevel(linear);
        });

        document.addEventListener('pointercancel', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            this.dragState = null;
            revertBusMasterLevel(this.strip);
        });
    }
}

export function initMonitorsBusMasterControls(root = document) {
    root.querySelectorAll('[data-monitors-send-control]').forEach((control) => {
        if (control.dataset.monitorsBusMasterControlBound === 'true') {
            return;
        }

        control.dataset.monitorsBusMasterControlBound = 'true';
        new MonitorsBusMasterControl(control);
    });
}

function bootMonitorsBusMasterControls() {
    initMonitorsBusMasterControls();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootMonitorsBusMasterControls);
} else {
    bootMonitorsBusMasterControls();
}
