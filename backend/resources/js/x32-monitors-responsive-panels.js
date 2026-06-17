const DESKTOP_MQ = window.matchMedia('(min-width: 901px)');

function syncMonitorsCollapsibles(root = document) {
    root.querySelectorAll('[data-monitors-collapsible]').forEach((details) => {
        if (DESKTOP_MQ.matches) {
            details.setAttribute('open', '');

            return;
        }

        if (details.dataset.userToggled !== 'true') {
            details.removeAttribute('open');
        }
    });
}

function bindMonitorsCollapsibles(root = document) {
    root.querySelectorAll('[data-monitors-collapsible]').forEach((details) => {
        details.addEventListener('toggle', () => {
            if (!DESKTOP_MQ.matches) {
                details.dataset.userToggled = 'true';
            }
        });
    });

    DESKTOP_MQ.addEventListener('change', () => {
        root.querySelectorAll('[data-monitors-collapsible]').forEach((details) => {
            delete details.dataset.userToggled;
        });
        syncMonitorsCollapsibles(root);
    });

    syncMonitorsCollapsibles(root);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => bindMonitorsCollapsibles());
} else {
    bindMonitorsCollapsibles();
}
