function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function orderFromList(list) {
    return [...list.querySelectorAll('[data-playlist-item-id]')]
        .map((element) => Number.parseInt(element.dataset.playlistItemId ?? '', 10))
        .filter((id) => Number.isInteger(id) && id > 0);
}

function updateOrderBadges(list, positions) {
    list.querySelectorAll('[data-playlist-item-id]').forEach((element) => {
        const itemId = Number.parseInt(element.dataset.playlistItemId ?? '', 10);
        const badge = element.querySelector('[data-playlist-order-badge]');

        if (! badge || ! Number.isInteger(itemId)) {
            return;
        }

        const position = positions[itemId] ?? positions[String(itemId)];

        if (position !== undefined) {
            badge.textContent = String(position).padStart(2, '0');
        }
    });
}

function showFeedback(feedbackElement, message, isError = false) {
    if (! feedbackElement) {
        return;
    }

    feedbackElement.textContent = message;
    feedbackElement.hidden = false;
    feedbackElement.classList.toggle('esb-portal__error', isError);
    feedbackElement.classList.toggle('esb-portal__success', ! isError);
    feedbackElement.setAttribute('role', isError ? 'alert' : 'status');
}

export function initStudioPlaylistOrder(list) {
    if (! list || list.dataset.playlistReorderReady === 'true') {
        return;
    }

    list.dataset.playlistReorderReady = 'true';

    const reorderUrl = list.dataset.reorderUrl ?? '';
    const feedbackElement = document.getElementById('playlist-order-feedback');
    let draggingItem = null;
    let previousOrder = orderFromList(list);

    list.querySelectorAll('.esb-studio__playlist-drag-handle').forEach((handle) => {
        handle.addEventListener('dragstart', (event) => {
            const item = handle.closest('[data-playlist-item-id]');

            if (! item) {
                return;
            }

            draggingItem = item;
            item.classList.add('esb-studio__setlist-ribbon--dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', item.dataset.playlistItemId ?? '');
        });

        handle.addEventListener('dragend', () => {
            if (draggingItem) {
                draggingItem.classList.remove('esb-studio__setlist-ribbon--dragging');
            }

            draggingItem = null;
        });
    });

    list.querySelectorAll('[data-playlist-item-id]').forEach((item) => {
        item.addEventListener('dragover', (event) => {
            if (! draggingItem || draggingItem === item) {
                return;
            }

            event.preventDefault();

            const targetRect = item.getBoundingClientRect();
            const insertBefore = event.clientY < targetRect.top + targetRect.height / 2;

            if (insertBefore) {
                list.insertBefore(draggingItem, item);
            } else {
                list.insertBefore(draggingItem, item.nextElementSibling);
            }
        });

        item.addEventListener('drop', async (event) => {
            if (! draggingItem) {
                return;
            }

            event.preventDefault();

            const nextOrder = orderFromList(list);

            if (nextOrder.join(',') === previousOrder.join(',')) {
                return;
            }

            list.classList.add('esb-studio__playlist-list--saving');

            try {
                const response = await fetch(reorderUrl, {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ order: nextOrder }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(payload.message ?? 'Unable to save playlist order.');
                }

                previousOrder = nextOrder;
                updateOrderBadges(list, payload.positions ?? {});
                showFeedback(feedbackElement, 'Playlist order saved.');
            } catch (error) {
                previousOrder.forEach((itemId) => {
                    const element = list.querySelector(`[data-playlist-item-id="${itemId}"]`);

                    if (element) {
                        list.appendChild(element);
                    }
                });

                showFeedback(
                    feedbackElement,
                    error instanceof Error ? error.message : 'Unable to save playlist order.',
                    true,
                );
            } finally {
                list.classList.remove('esb-studio__playlist-list--saving');
            }
        });
    });
}
