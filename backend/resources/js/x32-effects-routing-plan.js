function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function routingPlanSection(root) {
    return root.closest('[data-effect-routing-plan]');
}

function collectRoutingPlanPayload(section) {
    const payload = {};

    section.querySelectorAll('[data-routing-plan-field]').forEach((field) => {
        const key = field.dataset.routingPlanField;
        const value = field.value.trim();

        if (key === 'default_return_level') {
            payload[key] = value === '' ? null : value;
            return;
        }

        if (key === 'notes') {
            payload[key] = value === '' ? null : value;
            return;
        }

        payload[key] = value === '' ? null : value;
    });

    payload.target_sections = [];
    section.querySelectorAll('[data-routing-plan-target-section]:checked').forEach((checkbox) => {
        payload.target_sections.push(checkbox.value);
    });

    return payload;
}

function confirmedRoutingPlanPayload(section) {
    return JSON.parse(section.dataset.confirmedRoutingPlan ?? '{}');
}

function storeConfirmedRoutingPlan(section, payload) {
    section.dataset.confirmedRoutingPlan = JSON.stringify(payload);
}

function updateRoutingSummary(section, item) {
    const mode = section.querySelector('[data-summary-routing-mode]');
    const targets = section.querySelector('[data-summary-target-sections]');
    const destination = section.querySelector('[data-summary-return-destination]');
    const level = section.querySelector('[data-summary-default-return-level]');

    if (mode) {
        mode.textContent = item.routing_mode_label ?? 'Not configured';
    }

    if (targets) {
        targets.textContent = item.target_sections_label ?? 'Not selected';
    }

    if (destination) {
        destination.textContent = item.return_destination_label ?? 'Not configured';
    }

    if (level) {
        level.textContent = item.default_return_level_label ?? '—';
    }
}

function showRoutingError(section, message) {
    const error = section.querySelector('[data-effect-routing-error]');

    if (error) {
        error.textContent = message;
        error.hidden = false;
    }
}

function clearRoutingError(section) {
    const error = section.querySelector('[data-effect-routing-error]');

    if (error) {
        error.textContent = '';
        error.hidden = true;
    }
}

function setRoutingPlanState(section, state) {
    section.querySelectorAll('[data-routing-plan-field], [data-routing-plan-target-section]').forEach((field) => {
        field.classList.remove('is-saving', 'is-saved', 'is-error');

        if (state) {
            field.classList.add(state);
        }
    });
}

function restoreRoutingPlanFields(section, previous) {
    section.querySelectorAll('[data-routing-plan-field]').forEach((field) => {
        const key = field.dataset.routingPlanField;

        if (previous[key] === undefined) {
            return;
        }

        field.value = previous[key] ?? '';
    });

    const previousSections = previous.target_sections ?? [];
    section.querySelectorAll('[data-routing-plan-target-section]').forEach((checkbox) => {
        checkbox.checked = previousSections.includes(checkbox.value);
    });
}

async function saveRoutingPlan(section) {
    const url = section.dataset.updateUrl;

    if (!url) {
        return;
    }

    const payload = collectRoutingPlanPayload(section);
    const previous = confirmedRoutingPlanPayload(section);

    if (JSON.stringify(payload) === JSON.stringify(previous)) {
        return;
    }

    clearRoutingError(section);
    setRoutingPlanState(section, 'is-saving');

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify(payload),
        });

        const body = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(body?.message ?? 'Routing plan could not be saved.');
        }

        storeConfirmedRoutingPlan(section, payload);
        updateRoutingSummary(section, body.item ?? {});
        setRoutingPlanState(section, 'is-saved');
        window.setTimeout(() => setRoutingPlanState(section, null), 900);
    } catch (error) {
        restoreRoutingPlanFields(section, previous);
        showRoutingError(section, error instanceof Error ? error.message : 'Routing plan could not be saved.');
        setRoutingPlanState(section, 'is-error');
        window.setTimeout(() => setRoutingPlanState(section, null), 1200);
    }
}

function initEffectsRoutingPlan() {
    document.querySelectorAll('[data-effect-routing-plan]').forEach((section) => {
        storeConfirmedRoutingPlan(section, collectRoutingPlanPayload(section));

        section.querySelectorAll('select[data-routing-plan-field]').forEach((field) => {
            field.addEventListener('change', () => saveRoutingPlan(section));
        });

        section.querySelectorAll('[data-routing-plan-target-section]').forEach((checkbox) => {
            checkbox.addEventListener('change', () => saveRoutingPlan(section));
        });

        const levelField = section.querySelector('[data-routing-plan-field="default_return_level"]');

        if (levelField) {
            levelField.addEventListener('blur', () => saveRoutingPlan(section));
            levelField.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    levelField.blur();
                }
            });
        }

        const notesField = section.querySelector('[data-routing-plan-field="notes"]');

        if (notesField) {
            notesField.addEventListener('blur', () => saveRoutingPlan(section));
        }
    });
}

document.addEventListener('DOMContentLoaded', initEffectsRoutingPlan);
