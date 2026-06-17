function setEqPanelCollapsed(panel, main, collapsed) {
    panel.classList.toggle('is-collapsed', collapsed);
    main?.classList.toggle('vx32-monitors-main--eq-collapsed', collapsed);
    main?.classList.toggle('vx32-monitors-main--eq-focus', !collapsed);

    const toggle = panel.querySelector('[data-eq-panel-toggle]');

    if (toggle) {
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.textContent = collapsed ? 'Show' : 'Hide';
    }
}

export function initEqPanelToggles(root = document) {
    root.querySelectorAll('[data-eq-panel]').forEach((panel) => {
        const toggle = panel.querySelector('[data-eq-panel-toggle]');

        if (!toggle || toggle.dataset.eqPanelToggleBound === 'true') {
            return;
        }

        toggle.dataset.eqPanelToggleBound = 'true';

        const main = panel.closest('[data-monitors-main]');

        toggle.addEventListener('click', () => {
            const collapsed = !panel.classList.contains('is-collapsed');
            setEqPanelCollapsed(panel, main, collapsed);
        });
    });
}

function bootEqPanelToggles() {
    initEqPanelToggles();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootEqPanelToggles);
} else {
    bootEqPanelToggles();
}

export { setEqPanelCollapsed };
