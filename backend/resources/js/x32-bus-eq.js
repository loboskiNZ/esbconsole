import {
    commitEqBandParameter,
    commitEqMasterOn,
    eqControlConfig,
    getEqControlRoot,
} from './x32-monitors-eq-api';

const MODE_FIELDS = {
    LCUT: ['frequency'],
    HCUT: ['frequency'],
    LSHV: ['frequency', 'gain'],
    HSHV: ['frequency', 'gain'],
    VEQ: ['frequency', 'gain', 'q'],
    PEQ: ['frequency', 'gain', 'q'],
};

const GAIN_MIN = -15;
const GAIN_MAX = 15;
const FREQ_MIN = 20;
const FREQ_MAX = 20000;
const GRAPH_WIDTH = 640;
const GRAPH_HEIGHT = 180;
const DEFAULT_Q = 2;

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function parseFrequencyInput(raw) {
    const value = String(raw ?? '').trim().replace(/\s+/g, '');

    if (value === '') {
        return null;
    }

    const kMatch = value.match(/^(\d+(?:\.\d+)?)[kK](\d{1,2})?$/);

    if (kMatch) {
        const whole = parseFloat(kMatch[1]);
        const fraction = kMatch[2] ? parseInt(kMatch[2], 10) / 100 : 0;

        return (whole + fraction) * 1000;
    }

    const numeric = parseFloat(value.replace(/[^0-9.+-]/g, ''));

    return Number.isFinite(numeric) ? numeric : null;
}

function parseGainInput(raw) {
    const value = String(raw ?? '').trim().replace(/db$/i, '').replace(/\s+/g, '');

    if (value === '' || value === '0' || value === '+0' || value === '-0') {
        return 0;
    }

    const numeric = parseFloat(value);

    return Number.isFinite(numeric) ? clamp(numeric, GAIN_MIN, GAIN_MAX) : null;
}

function parseQInput(raw) {
    const numeric = parseFloat(String(raw ?? '').trim());

    return Number.isFinite(numeric) ? Math.max(0.1, numeric) : DEFAULT_Q;
}

function formatGainInput(gainDb) {
    if (Math.abs(gainDb) < 0.05) {
        return '0';
    }

    const prefix = gainDb > 0 ? '+' : '';

    return `${prefix}${gainDb.toFixed(1)}`;
}

function formatFrequencyInput(frequencyHz) {
    const hz = clamp(frequencyHz, FREQ_MIN, FREQ_MAX);

    if (hz >= 1000) {
        const khz = hz / 1000;
        const whole = Math.floor(khz);
        const fraction = Math.round((khz - whole) * 100);

        return `${whole}K${String(fraction).padStart(2, '0')}`;
    }

    return String(parseFloat(hz.toFixed(1))).replace(/\.0$/, '');
}

function frequencyToGraphX(frequency) {
    const ratio = Math.log(Math.max(FREQ_MIN, frequency) / FREQ_MIN) / Math.log(FREQ_MAX / FREQ_MIN);

    return ratio * GRAPH_WIDTH;
}

function gainToGraphY(gainDb) {
    const clamped = clamp(gainDb, GAIN_MIN, GAIN_MAX);
    const normalized = (clamped - GAIN_MIN) / (GAIN_MAX - GAIN_MIN);

    return 150 - (normalized * 120);
}

function graphYToGain(y) {
    const normalized = (150 - clamp(y, 30, 150)) / 120;

    return clamp(GAIN_MIN + (normalized * (GAIN_MAX - GAIN_MIN)), GAIN_MIN, GAIN_MAX);
}

function graphXToFrequency(x) {
    const ratio = clamp(x, 0, GRAPH_WIDTH) / GRAPH_WIDTH;

    return clamp(FREQ_MIN * ((FREQ_MAX / FREQ_MIN) ** ratio), FREQ_MIN, FREQ_MAX);
}

function modeSupportsGain(mode) {
    return (MODE_FIELDS[mode] ?? MODE_FIELDS.PEQ).includes('gain');
}

function bellContribution(frequency, centerHz, gainDb, q) {
    if (centerHz <= 0 || Math.abs(gainDb) < 0.01) {
        return 0;
    }

    const octaves = Math.log2(frequency / centerHz);
    const width = Math.max(0.25, 1 / q);

    return gainDb * Math.exp(-0.5 * (octaves / width) ** 2);
}

function approximateGainAtFrequency(frequency, bands) {
    let gain = 0;

    for (const band of bands) {
        const mode = band.mode;
        const centerHz = band.frequencyHz;

        if (!Number.isFinite(centerHz) || centerHz <= 0) {
            continue;
        }

        if (mode === 'LCUT' && frequency <= centerHz) {
            const ratio = Math.max(0, frequency / Math.max(1, centerHz));
            gain -= (1 - ratio) * 12;

            continue;
        }

        if (mode === 'HCUT' && frequency >= centerHz) {
            const ratio = Math.max(0, centerHz / Math.max(1, frequency));
            gain -= (1 - ratio) * 12;

            continue;
        }

        const gainDb = band.gainDb ?? 0;

        if (mode === 'VEQ' || mode === 'PEQ') {
            gain += bellContribution(frequency, centerHz, gainDb, band.q ?? DEFAULT_Q);
        } else if (mode === 'LSHV' || mode === 'HSHV') {
            gain += bellContribution(frequency, centerHz, gainDb, 0.71);
        }
    }

    return clamp(gain, GAIN_MIN, GAIN_MAX);
}

function buildCurvePath(bands) {
    const points = [];

    for (let step = 0; step <= 80; step += 1) {
        const ratio = step / 80;
        const frequency = FREQ_MIN * ((FREQ_MAX / FREQ_MIN) ** ratio);
        const gain = approximateGainAtFrequency(frequency, bands);
        points.push({
            x: frequencyToGraphX(frequency),
            y: gainToGraphY(gain),
        });
    }

    if (points.length === 0) {
        return '';
    }

    let path = `M${points[0].x.toFixed(2)},${points[0].y.toFixed(2)}`;

    for (let index = 1; index < points.length; index += 1) {
        path += ` L${points[index].x.toFixed(2)},${points[index].y.toFixed(2)}`;
    }

    return path;
}

function applyModeVisibility(strip, mode) {
    const visible = MODE_FIELDS[mode] ?? MODE_FIELDS.PEQ;

    strip.querySelectorAll('[data-eq-field]').forEach((field) => {
        const name = field.getAttribute('data-eq-field');
        const show = visible.includes(name);

        field.hidden = !show;
        field.toggleAttribute('hidden', !show);
    });
}

function readBandFromStrip(strip) {
    const mode = strip.querySelector('[data-eq-mode-select]')?.value ?? 'PEQ';
    const frequencyInput = strip.querySelector('[data-eq-input="frequency"]');
    const gainInput = strip.querySelector('[data-eq-input="gain"]');
    const qInput = strip.querySelector('[data-eq-input="q"]');

    return {
        number: parseInt(strip.getAttribute('data-eq-band') ?? '0', 10),
        mode,
        frequencyHz: parseFrequencyInput(frequencyInput?.value) ?? FREQ_MIN,
        gainDb: parseGainInput(gainInput?.value) ?? 0,
        q: parseQInput(qInput?.value),
    };
}

function svgPointFromClient(svg, clientX, clientY) {
    const rect = svg.getBoundingClientRect();
    const x = ((clientX - rect.left) / rect.width) * GRAPH_WIDTH;
    const y = ((clientY - rect.top) / rect.height) * GRAPH_HEIGHT;

    return { x, y };
}

class BusEqWorkspace {
    constructor(root) {
        this.root = root;
        this.controlRoot = getEqControlRoot();
        this.eqControl = eqControlConfig(this.controlRoot);
        this.svg = root.querySelector('[data-eq-graph]');
        this.curve = root.querySelector('[data-eq-curve]');
        this.strips = [...root.querySelectorAll('[data-eq-band-strip]')];
        this.handles = new Map(
            [...root.querySelectorAll('[data-eq-handle]')].map((handle) => [
                parseInt(handle.getAttribute('data-eq-band') ?? '0', 10),
                handle,
            ]),
        );
        this.dragState = null;
        this.commitPending = false;

        this.bind();
        this.refresh();
    }

    liveControlEnabled() {
        return this.eqControl.available;
    }

    async commitBand(strip, parameter, value) {
        if (!this.liveControlEnabled() || this.commitPending) {
            return null;
        }

        this.commitPending = true;

        try {
            const payload = await commitEqBandParameter(this.controlRoot, strip, parameter, value);
            this.refresh();

            return payload;
        } finally {
            this.commitPending = false;
        }
    }

    async commitBandFromStrip(strip, parameter) {
        const band = readBandFromStrip(strip);

        if (parameter === 'type') {
            return this.commitBand(strip, 'type', band.mode);
        }

        if (parameter === 'f') {
            return this.commitBand(strip, 'f', band.frequencyHz);
        }

        if (parameter === 'g') {
            return this.commitBand(strip, 'g', band.gainDb);
        }

        if (parameter === 'q') {
            return this.commitBand(strip, 'q', band.q);
        }

        return null;
    }

    bands() {
        return this.strips.map((strip) => readBandFromStrip(strip));
    }

    stripForBand(number) {
        return this.strips.find((strip) => parseInt(strip.getAttribute('data-eq-band') ?? '0', 10) === number);
    }

    refresh() {
        const bands = this.bands();

        if (this.curve) {
            this.curve.setAttribute('d', buildCurvePath(bands));
        }

        for (const band of bands) {
            const handle = this.handles.get(band.number);

            if (!handle) {
                continue;
            }

            const gainDb = ['LCUT', 'HCUT'].includes(band.mode) ? 0 : band.gainDb;
            handle.setAttribute('cx', frequencyToGraphX(band.frequencyHz).toFixed(2));
            handle.setAttribute('cy', gainToGraphY(gainDb).toFixed(2));
            handle.dataset.eqGainDraggable = modeSupportsGain(band.mode) ? 'true' : 'false';
        }
    }

    bind() {
        for (const strip of this.strips) {
            if (strip.dataset.eqStripBound === 'true') {
                continue;
            }

            strip.dataset.eqStripBound = 'true';

            const modeSelect = strip.querySelector('[data-eq-mode-select]');
            modeSelect?.addEventListener('change', async () => {
                applyModeVisibility(strip, modeSelect.value);
                this.refresh();

                if (this.liveControlEnabled()) {
                    await this.commitBandFromStrip(strip, 'type');
                }
            });

            applyModeVisibility(strip, modeSelect?.value ?? 'PEQ');

            strip.querySelectorAll('[data-eq-input]').forEach((input) => {
                input.addEventListener('change', async () => {
                    this.refresh();

                    if (!this.liveControlEnabled()) {
                        return;
                    }

                    const parameter = input.dataset.eqInput === 'frequency'
                        ? 'f'
                        : input.dataset.eqInput === 'gain'
                            ? 'g'
                            : 'q';

                    if (parameter === 'q' && !(MODE_FIELDS[strip.querySelector('[data-eq-mode-select]')?.value ?? 'PEQ'] ?? []).includes('q')) {
                        return;
                    }

                    await this.commitBandFromStrip(strip, parameter);
                });
                input.addEventListener('input', () => {
                    if (input.dataset.eqInput === 'gain' || input.dataset.eqInput === 'frequency') {
                        this.refresh();
                    }
                });
            });
        }

        if (this.controlRoot && this.controlRoot.dataset.eqMasterBound !== 'true') {
            this.controlRoot.dataset.eqMasterBound = 'true';

            this.controlRoot.querySelector('[data-eq-master-toggle]')?.addEventListener('click', async (event) => {
                event.preventDefault();

                if (!this.liveControlEnabled()) {
                    return;
                }

                const button = event.currentTarget;
                const targetEnabled = !button.classList.contains('is-on');

                await commitEqMasterOn(this.controlRoot, targetEnabled);
            });
        }

        if (!this.svg || this.svg.dataset.eqGraphBound === 'true') {
            return;
        }

        this.svg.dataset.eqGraphBound = 'true';

        this.svg.addEventListener('pointerdown', (event) => {
            const handle = event.target.closest('[data-eq-handle]');

            if (!handle) {
                return;
            }

            event.preventDefault();
            handle.setPointerCapture(event.pointerId);

            this.dragState = {
                band: parseInt(handle.getAttribute('data-eq-band') ?? '0', 10),
                pointerId: event.pointerId,
            };
        });

        this.svg.addEventListener('pointermove', (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            const strip = this.stripForBand(this.dragState.band);

            if (!strip) {
                return;
            }

            const point = svgPointFromClient(this.svg, event.clientX, event.clientY);
            const mode = strip.querySelector('[data-eq-mode-select]')?.value ?? 'PEQ';
            const frequencyHz = graphXToFrequency(point.x);
            const frequencyInput = strip.querySelector('[data-eq-input="frequency"]');

            if (frequencyInput) {
                frequencyInput.value = formatFrequencyInput(frequencyHz);
            }

            if (modeSupportsGain(mode)) {
                const gainDb = graphYToGain(point.y);
                const gainInput = strip.querySelector('[data-eq-input="gain"]');

                if (gainInput) {
                    gainInput.value = formatGainInput(gainDb);
                }
            }

            this.refresh();
        });

        const endDrag = async (event) => {
            if (!this.dragState || event.pointerId !== this.dragState.pointerId) {
                return;
            }

            const strip = this.stripForBand(this.dragState.band);

            this.dragState = null;

            if (!strip || !this.liveControlEnabled()) {
                return;
            }

            await this.commitBandFromStrip(strip, 'f');

            const mode = strip.querySelector('[data-eq-mode-select]')?.value ?? 'PEQ';

            if (modeSupportsGain(mode)) {
                await this.commitBandFromStrip(strip, 'g');
            }
        };

        this.svg.addEventListener('pointerup', endDrag);
        this.svg.addEventListener('pointercancel', endDrag);
    }
}

export function initBusEqWorkspaces(root = document) {
    root.querySelectorAll('[data-eq-workspace]').forEach((workspace) => {
        if (workspace.dataset.eqWorkspaceBound === 'true') {
            return;
        }

        workspace.dataset.eqWorkspaceBound = 'true';
        new BusEqWorkspace(workspace);
    });
}

function bootBusEqWorkspaces() {
    initBusEqWorkspaces();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootBusEqWorkspaces);
} else {
    bootBusEqWorkspaces();
}
