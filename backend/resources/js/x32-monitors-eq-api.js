function csrfToken() {
    return document.querySelector('meta[name=csrf-token]')?.content ?? '';
}

export function getEqControlRoot() {
    return document.querySelector('[data-monitors-eq-control]');
}

export function eqControlConfig(root = getEqControlRoot()) {
    return {
        updateUrl: root?.dataset.eqControlUrl ?? '',
        available: root?.dataset.eqLiveControl === 'true' && root?.dataset.eqLearned === 'true',
        learned: root?.dataset.eqLearned === 'true',
        reason: root?.dataset.eqControlReason ?? '',
    };
}

export async function writeMonitorBusEq(root, parameter, value, band = null) {
    const { updateUrl, available } = eqControlConfig(root);

    if (!available || !updateUrl) {
        return {
            success: false,
            error: 'Live monitor bus EQ control is not available.',
        };
    }

    const body = {
        parameter,
        value,
    };

    if (band !== null && band !== undefined) {
        body.band = band;
    }

    try {
        const response = await fetch(updateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(body),
        });

        const payload = await response.json();

        if (!response.ok) {
            return {
                success: false,
                error: payload?.message ?? payload?.error ?? `Monitor bus EQ write failed (${response.status}).`,
            };
        }

        return payload;
    } catch (error) {
        return {
            success: false,
            error: error instanceof Error ? error.message : 'Monitor bus EQ write failed.',
        };
    }
}

export function applyEqMasterVisual(root, enabled) {
    const button = root?.querySelector('[data-eq-master-toggle]');

    if (!button) {
        return;
    }

    button.classList.toggle('is-on', enabled);
    button.textContent = enabled ? 'ON' : 'OFF';
    button.setAttribute('aria-label', `Bus master EQ bypass · ${enabled ? 'ON' : 'OFF'}`);
    root.classList.remove('is-eq-error');
    delete root.dataset.eqError;
}

export function showEqError(root, message) {
    root.classList.add('is-eq-error');
    root.dataset.eqError = message;
}

export function applyBandConfirmedDisplay(strip, parameter, displayValue) {
    if (!strip || displayValue === undefined || displayValue === null) {
        return;
    }

    if (parameter === 'type') {
        const modeSelect = strip.querySelector('[data-eq-mode-select]');

        if (modeSelect) {
            modeSelect.value = String(displayValue);
        }

        return;
    }

    const input = strip.querySelector(`[data-eq-input="${parameter === 'f' ? 'frequency' : parameter === 'g' ? 'gain' : 'q'}"]`);

    if (input) {
        input.value = String(displayValue);
    }
}

export async function commitEqMasterOn(root, targetEnabled) {
    const previousEnabled = root.querySelector('[data-eq-master-toggle]')?.classList.contains('is-on') === true;

    applyEqMasterVisual(root, targetEnabled);

    const payload = await writeMonitorBusEq(root, 'on', targetEnabled);

    if (!payload?.success) {
        applyEqMasterVisual(root, previousEnabled);
        showEqError(root, payload?.error ?? 'Monitor bus EQ on was not confirmed.');

        return payload;
    }

    const confirmedEnabled = payload.display_value === 'ON';
    applyEqMasterVisual(root, confirmedEnabled);

    return payload;
}

export async function commitEqBandParameter(root, strip, parameter, value) {
    const band = parseInt(strip.getAttribute('data-eq-band') ?? '0', 10);
    const inputKey = parameter === 'f' ? 'frequency' : parameter === 'g' ? 'gain' : parameter === 'q' ? 'q' : null;
    const input = inputKey ? strip.querySelector(`[data-eq-input="${inputKey}"]`) : null;
    const modeSelect = strip.querySelector('[data-eq-mode-select]');
    const previousValue = parameter === 'type'
        ? modeSelect?.value
        : input?.value;

    const payload = await writeMonitorBusEq(root, parameter, value, band);

    if (!payload?.success) {
        if (parameter === 'type' && modeSelect && previousValue !== undefined) {
            modeSelect.value = previousValue;
        } else if (input && previousValue !== undefined) {
            input.value = previousValue;
        }

        strip.classList.add('is-eq-band-error');
        strip.dataset.eqError = payload?.error ?? 'Monitor bus EQ write was not confirmed.';

        return payload;
    }

    applyBandConfirmedDisplay(strip, parameter, payload.display_value);
    strip.classList.remove('is-eq-band-error');
    delete strip.dataset.eqError;

    return payload;
}
