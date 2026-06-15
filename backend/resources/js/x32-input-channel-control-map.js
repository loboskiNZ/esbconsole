/**
 * Client-side mirror of App\Services\X32\X32InputChannelControlMap.
 * Single source of truth for X32 input channel OSC paths in the UI layer.
 */
export const X32_INPUT_CHANNEL_CONTROLS = {
    fader: {
        label: 'Fader',
        oscTemplate: '/ch/{NN}/mix/fader',
        valueType: 'float',
        min: 0,
        max: 1,
        write: true,
        uiTransform: 'fader_db',
    },
    mute: {
        label: 'Mute',
        oscTemplate: '/ch/{NN}/mix/on',
        valueType: 'bool',
        write: true,
        invertOsc: true,
    },
    pan: {
        label: 'Pan',
        oscTemplate: '/ch/{NN}/mix/pan',
        valueType: 'float',
        min: 0,
        max: 1,
        write: true,
        uiTransform: 'pan_lr',
    },
    main_lr: {
        label: 'Main L/R',
        oscTemplate: '/ch/{NN}/mix/st',
        valueType: 'bool',
        write: true,
    },
    gate_on: {
        label: 'Gate',
        oscTemplate: '/ch/{NN}/gate/on',
        valueType: 'bool',
        write: true,
    },
    compressor_on: {
        label: 'Compressor',
        oscTemplate: '/ch/{NN}/dyn/on',
        valueType: 'bool',
        write: true,
    },
    eq_on: {
        label: 'EQ',
        oscTemplate: '/ch/{NN}/eq/on',
        valueType: 'bool',
        write: true,
    },
    sends: {
        label: 'Sends',
        valueType: 'bool',
        write: false,
        uiOnly: true,
    },
    gain: {
        label: 'Gain',
        valueType: 'float',
        write: false,
        headampDependent: true,
        uiTransform: 'gain_db',
    },
    phantom48v: {
        label: '48V',
        valueType: 'bool',
        write: false,
        headampDependent: true,
    },
    stereo_link: {
        label: 'Link',
        valueType: 'bool',
        write: false,
        uiOnly: true,
    },
};

export function formatOscChannel(channelNumber) {
    return String(channelNumber).padStart(2, '0');
}

export function oscPathForControl(controlKey, channelNumber) {
    const definition = X32_INPUT_CHANNEL_CONTROLS[controlKey];

    if (! definition?.oscTemplate || definition.headampDependent) {
        return null;
    }

    return definition.oscTemplate.replace('{NN}', formatOscChannel(channelNumber));
}

export function formatPanDisplay(pan) {
    const value = Math.min(1, Math.max(0, pan));

    if (Math.abs(value - 0.5) < 0.01) {
        return 'C';
    }

    if (value < 0.5) {
        return `L${Math.round((0.5 - value) * 200)}`;
    }

    return `R${Math.round((value - 0.5) * 200)}`;
}

export function formatGainDisplay(gain) {
    if (gain === null || gain === undefined) {
        return '0.0';
    }

    const db = (gain * 60) - 12;

    return `${db >= 0 ? '+' : ''}${db.toFixed(1)}`;
}

export const FADER_SCALE_TICKS = [
    { db: 10, linear: 1.0, label: '+10' },
    { db: 5, linear: 0.875, label: '5' },
    { db: 0, linear: 0.75, label: '0', unity: true },
    { db: -5, linear: 0.625, label: '-5' },
    { db: -10, linear: 0.5, label: '-10' },
    { db: -20, linear: 0.375, label: '-20' },
    { db: -30, linear: 0.25, label: '-30' },
    { db: -40, linear: 0.1875, label: '-40' },
    { db: -60, linear: 0.0625, label: '-60' },
];
