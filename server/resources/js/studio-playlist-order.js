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

    list.addEventListener('dragstart', (event) => {
        const badge = event.target.closest('.esb-studio__setlist-order-badge--draggable');

        if (! badge) {
            return;
        }

        const item = badge.closest('[data-playlist-item-id]');

        if (! item) {
            return;
        }

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
    });

    list.addEventListener('dragend', () => {
        clearDragState();
    });

    list.addEventListener('dragover', (event) => {
        if (! draggingItem || ! placeholder) {
            return;
        }

        event.preventDefault();

        const targetItem = event.target.closest('[data-playlist-item-id]');

        if (! targetItem || targetItem === draggingItem) {
            return;
        }

        const targetRect = targetItem.getBoundingClientRect();
        const insertBefore = event.clientY < targetRect.top + targetRect.height / 2;

        if (insertBefore) {
            list.insertBefore(placeholder, targetItem);
        } else {
            list.insertBefore(placeholder, targetItem.nextElementSibling);
        }
    });

    list.addEventListener('drop', async (event) => {
        if (! draggingItem || ! placeholder) {
            return;
        }

        event.preventDefault();

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
}
