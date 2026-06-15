import Alpine from 'alpinejs';
import { formatDb, linearMarkPercent, linearToDb, quantizeLinear } from './x32-fader-scale';
import {
    FADER_SCALE_TICKS,
    formatGainDisplay,
    formatPanDisplay,
    X32_INPUT_CHANNEL_CONTROLS,
} from './x32-input-channel-control-map';

const WRITE_THROTTLE_MS = 35;

function stripFieldForControl(controlKey) {
    return {
        fader: 'faderLevel',
        mute: 'muted',
        pan: 'pan',
        main_lr: 'mainLr',
        gate_on: 'gateOn',
        compressor_on: 'compressorOn',
        eq_on: 'eqOn',
        sends: 'sendsOpen',
        gain: 'gain',
        phantom48v: 'phantom48v',
        stereo_link: 'linked',
    }[controlKey] ?? controlKey;
}

Alpine.data('virtualConsole', (config) => ({
    strips: config.strips ?? [],
    controlUpdateUrl: config.controlUpdateUrl ?? '',
    activeTab: 'overview',
    activeLayer: 'ch1_32',
    saving: false,
    localControlKeys: {},
    pendingWrites: {},
    faderScaleTicks: FADER_SCALE_TICKS,

    stripByChannel(channelNumber) {
        return this.strips.find((strip) => strip.channelNumber === channelNumber);
    },

    controlKey(channelNumber, controlKey) {
        return `${channelNumber}:${controlKey}`;
    },

    isLocallyControlled(channelNumber, controlKey) {
        return Boolean(this.localControlKeys[this.controlKey(channelNumber, controlKey)]);
    },

    patchStrip(channelNumber, patch) {
        const index = this.strips.findIndex((strip) => strip.channelNumber === channelNumber);

        if (index === -1) {
            return null;
        }

        const next = { ...this.strips[index], ...patch };
        this.strips.splice(index, 1, next);

        return next;
    },

    faderDb(strip) {
        return linearToDb(strip?.faderLevel ?? 0);
    },

    faderDbLabel(strip) {
        return formatDb(this.faderDb(strip));
    },

    panLabel(strip) {
        return formatPanDisplay(strip?.pan ?? 0.5);
    },

    gainLabel(strip) {
        return formatGainDisplay(strip?.gain ?? 0.5);
    },

    meterHeight(strip) {
        return `${Math.max(2, linearMarkPercent(strip?.meterLevel ?? 0))}%`;
    },

    faderCapStyle(strip) {
        const pct = linearMarkPercent(strip?.faderLevel ?? 0);

        return { bottom: `calc(${pct}% - 9px)` };
    },

    faderFillStyle(strip) {
        const pct = linearMarkPercent(strip?.faderLevel ?? 0);

        return { height: `${pct}%` };
    },

    tickStyle(tick) {
        return { bottom: `${linearMarkPercent(tick.linear)}%` };
    },

    setStripValue(channelNumber, controlKey, value) {
        const field = stripFieldForControl(controlKey);
        const patch = { [field]: value };

        if (controlKey === 'fader') {
            patch.meterLevel = Math.min(1, Math.max(0, (linearToDb(value) + 60) / 70));
        }

        this.patchStrip(channelNumber, patch);
    },

    beginLocalControl(channelNumber, controlKey) {
        this.localControlKeys[this.controlKey(channelNumber, controlKey)] = true;
        this.patchStrip(channelNumber, { isLocallyControlled: true });
    },

    endLocalControl(channelNumber, controlKey) {
        delete this.localControlKeys[this.controlKey(channelNumber, controlKey)];

        const stillLocal = Object.keys(this.localControlKeys).some((key) => key.startsWith(`${channelNumber}:`));

        if (! stillLocal) {
            this.patchStrip(channelNumber, { isLocallyControlled: false });
        }
    },

    queueControlWrite(channelNumber, controlKey, value, { final = false } = {}) {
        const writeKey = this.controlKey(channelNumber, controlKey);

        if (this.pendingWrites[writeKey]) {
            clearTimeout(this.pendingWrites[writeKey]);
        }

        const definition = X32_INPUT_CHANNEL_CONTROLS[controlKey];
        const shouldPersist = Boolean(definition?.write)
            || Boolean(definition?.uiOnly)
            || Boolean(definition?.headampDependent);

        if (! shouldPersist) {
            return;
        }

        const dispatch = () => {
            delete this.pendingWrites[writeKey];
            this.sendControl(channelNumber, controlKey, value);
        };

        if (final) {
            dispatch();

            return;
        }

        this.pendingWrites[writeKey] = setTimeout(dispatch, WRITE_THROTTLE_MS);
    },

    async sendControl(channelNumber, controlKey, value) {
        if (! this.controlUpdateUrl) {
            return;
        }

        this.saving = true;

        try {
            const response = await fetch(this.controlUpdateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    control_key: controlKey,
                    channel: channelNumber,
                    value,
                }),
            });

            if (response.ok && ! this.isLocallyControlled(channelNumber, controlKey)) {
                const field = stripFieldForControl(controlKey);
                const strip = this.stripByChannel(channelNumber);

                if (strip) {
                    this.patchStrip(channelNumber, {
                        lastConfirmedState: { ...strip.lastConfirmedState, [field]: value },
                    });
                }
            }
        } finally {
            this.saving = false;
        }
    },

    updateControl(channelNumber, controlKey, value, { final = false, local = true } = {}) {
        if (local) {
            this.setStripValue(channelNumber, controlKey, value);
        }

        this.queueControlWrite(channelNumber, controlKey, value, { final });
    },

    toggleBoolControl(channelNumber, controlKey) {
        const strip = this.stripByChannel(channelNumber);

        if (! strip) {
            return;
        }

        const field = stripFieldForControl(controlKey);
        const nextValue = ! strip[field];
        this.beginLocalControl(channelNumber, controlKey);
        this.updateControl(channelNumber, controlKey, nextValue, { final: true });
        this.endLocalControl(channelNumber, controlKey);
    },

    pointerDownFader(event, channelNumber) {
        event.preventDefault();
        this.beginLocalControl(channelNumber, 'fader');
        this.moveFader(event, channelNumber);

        event.currentTarget.setPointerCapture?.(event.pointerId);
    },

    pointerMoveFader(event, channelNumber) {
        if (! this.isLocallyControlled(channelNumber, 'fader')) {
            return;
        }

        event.preventDefault();
        this.moveFader(event, channelNumber);
    },

    pointerUpFader(event, channelNumber) {
        if (! this.isLocallyControlled(channelNumber, 'fader')) {
            return;
        }

        event.currentTarget.releasePointerCapture?.(event.pointerId);

        const strip = this.stripByChannel(channelNumber);
        const value = quantizeLinear(strip?.faderLevel ?? 0);
        this.setStripValue(channelNumber, 'fader', value);
        this.updateControl(channelNumber, 'fader', value, { final: true });
        this.endLocalControl(channelNumber, 'fader');
    },

    moveFader(event, channelNumber) {
        const track = event.currentTarget.closest('.vx32-fader-track') ?? event.currentTarget;
        const rect = track.getBoundingClientRect();

        if (rect.height <= 0) {
            return;
        }

        const ratio = 1 - ((event.clientY - rect.top) / rect.height);
        const value = quantizeLinear(ratio);
        this.updateControl(channelNumber, 'fader', value, { final: false });
    },

    pointerDownKnob(event, channelNumber, controlKey) {
        event.preventDefault();
        this.beginLocalControl(channelNumber, controlKey);
        this.moveKnob(event, channelNumber, controlKey);
        event.currentTarget.setPointerCapture?.(event.pointerId);
    },

    pointerMoveKnob(event, channelNumber, controlKey) {
        if (! this.isLocallyControlled(channelNumber, controlKey)) {
            return;
        }

        event.preventDefault();
        this.moveKnob(event, channelNumber, controlKey);
    },

    pointerUpKnob(event, channelNumber, controlKey) {
        if (! this.isLocallyControlled(channelNumber, controlKey)) {
            return;
        }

        event.currentTarget.releasePointerCapture?.(event.pointerId);
        const strip = this.stripByChannel(channelNumber);
        const field = stripFieldForControl(controlKey);
        this.updateControl(channelNumber, controlKey, strip?.[field], { final: true });
        this.endLocalControl(channelNumber, controlKey);
    },

    moveKnob(event, channelNumber, controlKey) {
        const knob = event.currentTarget;
        const rect = knob.getBoundingClientRect();
        const centerX = rect.left + (rect.width / 2);
        const centerY = rect.top + (rect.height / 2);
        const angle = Math.atan2(event.clientY - centerY, event.clientX - centerX);
        let normalized = (angle + (Math.PI / 2)) / Math.PI;

        if (normalized < 0) {
            normalized += 1;
        }

        if (normalized > 1) {
            normalized -= 1;
        }

        const value = Math.min(1, Math.max(0, normalized));
        this.updateControl(channelNumber, controlKey, value, { final: false });
    },

    knobRotation(strip, controlKey) {
        const field = stripFieldForControl(controlKey);
        const value = strip?.[field] ?? 0.5;

        return { transform: `rotate(${(value * 270) - 135}deg)` };
    },
}));
