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

function getDragAfterElement(list, y, draggingItem) {
    const siblings = [...list.querySelectorAll('[data-playlist-item-id]')]
        .filter((element) => element !== draggingItem);

    return siblings.reduce(
        (closest, element) => {
            const box = element.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset, element };
            }

            return closest;
        },
        { offset: Number.NEGATIVE_INFINITY, element: null },
    ).element;
}

let feedbackFadeTimer = null;

function showFeedback(feedbackElement, message, isError = false) {
    if (! feedbackElement) {
        return;
    }

    clearTimeout(feedbackFadeTimer);
    feedbackElement.textContent = message;
    feedbackElement.hidden = false;
    feedbackElement.classList.toggle('esb-portal__error', isError);
    feedbackElement.classList.toggle('esb-portal__success', ! isError);
    feedbackElement.classList.toggle('esb-studio__playlist-order-feedback--visible', ! isError);
    feedbackElement.setAttribute('role', isError ? 'alert' : 'status');

    if (! isError) {
        feedbackFadeTimer = window.setTimeout(() => {
            feedbackElement.hidden = true;
            feedbackElement.textContent = '';
            feedbackElement.classList.remove('esb-studio__playlist-order-feedback--visible');
        }, 2000);
    }
}

function restoreOrder(list, previousOrder) {
    previousOrder.forEach((itemId) => {
        const element = list.querySelector(`[data-playlist-item-id="${itemId}"]`);

        if (element) {
            list.appendChild(element);
        }
    });
}

export function updatePlaylistSummary(summary) {
    if (! summary) {
        return;
    }

    const songs = document.querySelector('[data-playlist-summary-songs]');
    const parts = document.querySelector('[data-playlist-summary-parts]');
    const charts = document.querySelector('[data-playlist-summary-charts]');
    const duration = document.querySelector('[data-playlist-summary-duration]');

    if (songs && summary.song_count !== undefined) {
        songs.textContent = Number(summary.song_count).toLocaleString();
    }

    if (parts && summary.instrument_part_count !== undefined) {
        parts.textContent = Number(summary.instrument_part_count).toLocaleString();
    }

    if (charts && summary.charts_count !== undefined) {
        charts.textContent = Number(summary.charts_count).toLocaleString();
    }

    if (duration && summary.estimated_duration_label !== undefined) {
        duration.textContent = summary.estimated_duration_label;
    }
}

export function initStudioPlaylistOrder(list) {
    if (! list || list.dataset.playlistReorderReady === 'true') {
        return;
    }

    list.dataset.playlistReorderReady = 'true';

    const reorderUrl = list.dataset.reorderUrl ?? '';
    const feedbackElement = document.getElementById('playlist-order-feedback');
    let draggingItem = null;
    let placeholder = null;
    let previousOrder = orderFromList(list);
    let dropHandled = false;

    const clearDragState = () => {
        if (draggingItem) {
            draggingItem.classList.remove('esb-studio__setlist-ribbon--dragging');
        }

        if (placeholder?.parentNode) {
            placeholder.remove();
        }

        draggingItem = null;
        placeholder = null;
    };

    const movePlaceholder = (clientY) => {
        if (! placeholder) {
            return;
        }

        const afterElement = getDragAfterElement(list, clientY, draggingItem);

        if (afterElement === null) {
            list.appendChild(placeholder);
        } else {
            list.insertBefore(placeholder, afterElement);
        }
    };

    list.addEventListener('dragstart', (event) => {
        const handle = event.target.closest('.esb-studio__setlist-order-badge--draggable');

        if (! handle) {
            return;
        }

        const item = handle.closest('[data-playlist-item-id]');

        if (! item) {
            return;
        }

        dropHandled = false;
        draggingItem = item;
        previousOrder = orderFromList(list);
        placeholder = document.createElement('li');
        placeholder.className = 'esb-studio__playlist-drop-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.style.height = `${item.getBoundingClientRect().height}px`;
        list.insertBefore(placeholder, item);
        item.classList.add('esb-studio__setlist-ribbon--dragging');
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', item.dataset.playlistItemId ?? '');

        if (event.dataTransfer.setDragImage) {
            event.dataTransfer.setDragImage(item, 20, 20);
        }
    });

    list.addEventListener('dragenter', (event) => {
        if (! draggingItem || ! placeholder) {
            return;
        }

        event.preventDefault();
    });

    list.addEventListener('dragover', (event) => {
        if (! draggingItem || ! placeholder) {
            return;
        }

        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        movePlaceholder(event.clientY);
    });

    list.addEventListener('drop', async (event) => {
        if (! draggingItem || ! placeholder) {
            return;
        }

        event.preventDefault();
        dropHandled = true;

        list.insertBefore(draggingItem, placeholder);
        clearDragState();

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
            showFeedback(feedbackElement, '✓ Playlist order updated');
        } catch (error) {
            restoreOrder(list, previousOrder);
            showFeedback(
                feedbackElement,
                error instanceof Error ? error.message : 'Unable to save playlist order.',
                true,
            );
        } finally {
            list.classList.remove('esb-studio__playlist-list--saving');
        }
    });

    list.addEventListener('dragend', () => {
        if (! dropHandled && draggingItem && placeholder) {
            list.insertBefore(draggingItem, placeholder);
        }

        clearDragState();
        dropHandled = false;
    });
}

export function bootStudioPlaylistOrder() {
    initStudioPlaylistOrder(document.getElementById('playlist-sortable-list'));
}
