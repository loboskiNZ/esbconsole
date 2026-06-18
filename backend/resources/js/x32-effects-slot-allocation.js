import { refreshEffectDeployStateForCard } from './x32-effects-deploy';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function setSlotSelectState(select, state) {
    select.classList.remove('is-saving', 'is-saved', 'is-error');

    if (state) {
        select.classList.add(state);
    }
}

function slotAllocationRoot(select) {
    return select.closest('[data-effect-slot-allocation]');
}

function slotErrorElement(select) {
    return slotAllocationRoot(select)?.querySelector('[data-effect-slot-error]') ?? null;
}

function slotPillElement(select) {
    return slotAllocationRoot(select)?.querySelector('[data-effect-slot-pill]') ?? null;
}

function showSlotError(select, message) {
    const error = slotErrorElement(select);
    const root = slotAllocationRoot(select);

    if (error) {
        error.textContent = message;
        error.hidden = false;
    }

    if (root) {
        root.classList.remove('vx32-effects-workspace__slot-allocation--allocated', 'vx32-effects-workspace__slot-allocation--unallocated');
        root.classList.add('vx32-effects-workspace__slot-allocation--conflict');
    }
}

function clearSlotError(select) {
    const error = slotErrorElement(select);

    if (error) {
        error.textContent = '';
        error.hidden = true;
    }
}

function updateSlotPill(select, slotNumber) {
    const pill = slotPillElement(select);
    const summary = slotAllocationRoot(select)?.querySelector('[data-effect-slot-summary]');
    const summaryValue = summary?.querySelector('[data-effect-slot-summary-value]');
    const root = slotAllocationRoot(select);
    const value = pill?.querySelector('[data-effect-slot-pill-value]');

    if (!pill || !root) {
        return;
    }

    if (slotNumber) {
        if (value) {
            value.textContent = String(slotNumber);
        }

        if (summaryValue) {
            summaryValue.textContent = String(slotNumber);
        }

        if (summary) {
            summary.hidden = false;
        }

        pill.hidden = false;
        root.classList.remove('vx32-effects-workspace__slot-allocation--unallocated', 'vx32-effects-workspace__slot-allocation--conflict');
        root.classList.add('vx32-effects-workspace__slot-allocation--allocated');
        return;
    }

    pill.hidden = true;

    if (summary) {
        summary.hidden = true;
    }

    root.classList.remove('vx32-effects-workspace__slot-allocation--allocated', 'vx32-effects-workspace__slot-allocation--conflict');
    root.classList.add('vx32-effects-workspace__slot-allocation--unallocated');
}

function refreshPackageSlotOptions(packageId) {
    const selects = document.querySelectorAll(`[data-effect-slot-input][data-package-id="${packageId}"]`);
    const occupied = new Map();

    selects.forEach((select) => {
        const slot = select.dataset.confirmedValue ?? '';

        if (slot !== '') {
            occupied.set(slot, select.dataset.itemId);
        }
    });

    selects.forEach((select) => {
        const itemId = select.dataset.itemId;

        select.querySelectorAll('option[value]').forEach((option) => {
            if (option.value === '' || option.dataset.permanentReserved === '1') {
                return;
            }

            const occupantItemId = occupied.get(option.value);
            const disabled = occupantItemId !== undefined && occupantItemId !== itemId;

            option.disabled = disabled;
            option.dataset.samePackageLock = disabled ? '1' : '';
            option.textContent = disabled ? `FX${option.value} (in use)` : `FX${option.value}`;
        });
    });
}

async function saveSlotAllocation(select) {
    const url = select.dataset.updateUrl;
    const packageId = select.dataset.packageId;
    const itemId = select.dataset.itemId;

    if (!url) {
        return;
    }

    const previousValue = select.dataset.confirmedValue ?? '';
    const nextValue = select.value;

    if (nextValue === previousValue) {
        return;
    }

    clearSlotError(select);
    setSlotSelectState(select, 'is-saving');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                preferred_slot_number: nextValue === '' ? null : Number.parseInt(nextValue, 10),
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(payload?.message ?? 'Slot allocation could not be saved.');
        }

        const savedSlot = payload?.item?.preferred_slot_number ?? null;
        const confirmed = savedSlot === null ? '' : String(savedSlot);

        select.value = confirmed;
        select.dataset.confirmedValue = confirmed;
        updateSlotPill(select, savedSlot);
        refreshPackageSlotOptions(packageId);
        refreshEffectDeployStateForCard(select.closest('[data-effect-package-item-card]'));
        setSlotSelectState(select, 'is-saved');
        window.setTimeout(() => setSlotSelectState(select, null), 900);
    } catch (error) {
        select.value = previousValue;
        showSlotError(select, error instanceof Error ? error.message : 'Slot allocation could not be saved.');
        setSlotSelectState(select, 'is-error');
        window.setTimeout(() => setSlotSelectState(select, null), 1200);
    }
}

function initEffectsSlotAllocation() {
    document.querySelectorAll('[data-effect-slot-input]').forEach((select) => {
        select.dataset.confirmedValue = select.value;
        select.addEventListener('change', () => saveSlotAllocation(select));
    });
}

document.addEventListener('DOMContentLoaded', initEffectsSlotAllocation);
