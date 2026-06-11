import Sortable, { MultiDrag } from 'sortablejs';

Sortable.mount(new MultiDrag());

const DESKTOP_MEDIA_QUERY = '(min-width: 768px)';

function collectOrder(listElement) {
    return [...listElement.querySelectorAll('[data-playlist-item-id]')].map(
        (element) => parseInt(element.dataset.playlistItemId, 10),
    );
}

function updatePositionLabels(listElement) {
    listElement.querySelectorAll('[data-playlist-item-id]').forEach((element, index) => {
        const label = element.querySelector('[data-position-label]');

        if (label) {
            label.textContent = `${index + 1}.`;
        }
    });
}

async function persistOrder(listElement, reorderUrl, csrfToken) {
    const response = await fetch(reorderUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ order: collectOrder(listElement) }),
    });

    if (!response.ok) {
        throw new Error('Failed to save playlist order.');
    }

    return response.json();
}

export function initPlaylistSortable() {
    const listElement = document.getElementById('playlist-sortable-list');

    if (!listElement || !window.matchMedia(DESKTOP_MEDIA_QUERY).matches) {
        return;
    }

    const reorderUrl = listElement.dataset.reorderUrl;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    if (!reorderUrl || !csrfToken) {
        return;
    }

    let orderBeforeDrag = collectOrder(listElement);

    new Sortable(listElement, {
        handle: '.playlist-drag-handle',
        draggable: '.playlist-sortable-item',
        animation: 150,
        multiDrag: true,
        selectedClass: 'playlist-sortable-selected',
        avoidImplicitDeselect: true,
        onStart() {
            orderBeforeDrag = collectOrder(listElement);
        },
        onEnd: async () => {
            const orderAfterDrag = collectOrder(listElement);

            if (JSON.stringify(orderBeforeDrag) === JSON.stringify(orderAfterDrag)) {
                return;
            }

            try {
                const payload = await persistOrder(listElement, reorderUrl, csrfToken);
                updatePositionLabels(listElement);
                orderBeforeDrag = orderAfterDrag;

                listElement.dispatchEvent(
                    new CustomEvent('playlist-reorder-success', {
                        bubbles: true,
                        detail: { message: payload.message ?? 'Playlist order updated.' },
                    }),
                );
            } catch {
                listElement.dispatchEvent(
                    new CustomEvent('playlist-reorder-error', {
                        bubbles: true,
                        detail: { message: 'Could not save playlist order. Refresh and try again.' },
                    }),
                );

                window.location.reload();
            }
        },
    });
}
