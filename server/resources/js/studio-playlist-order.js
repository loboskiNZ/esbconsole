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

    const clearDragState = () => {
        if (draggingItem) {
            draggingItem.classList.remove('esb-studio__setlist-ribbon--dragging');
        }

        if (placeholder?.parentNode) {
            placeholder.remove();
        }

        list.classList.remove('esb-studio__playlist-list--dragging');
        document.body.classList.remove('esb-studio__playlist-drag-active');
        draggingItem = null;
        placeholder = null;
    };

    const repositionDraggedItem = (clientY) => {
        if (! draggingItem || ! placeholder) {
            return;
        }

        const afterElement = getDragAfterElement(list, clientY, draggingItem);

        if (afterElement === null) {
            list.appendChild(placeholder);
        } else {
            list.insertBefore(placeholder, afterElement);
        }

        if (placeholder.nextElementSibling !== draggingItem) {
            list.insertBefore(draggingItem, placeholder.nextElementSibling);
        }
    };

    const persistOrder = async () => {
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
    };

    const finishPointerDrag = async () => {
        if (! draggingItem || ! placeholder) {
            clearDragState();

            return;
        }

        clearDragState();
        await persistOrder();
    };

    list.addEventListener('pointerdown', (event) => {
        if (event.button !== 0) {
            return;
        }

        const handle = event.target.closest('[data-playlist-drag-handle]');

        if (! handle || ! list.contains(handle)) {
            return;
        }

        const item = handle.closest('[data-playlist-item-id]');

        if (! item) {
            return;
        }

        event.preventDefault();

        draggingItem = item;
        previousOrder = orderFromList(list);
        placeholder = document.createElement('li');
        placeholder.className = 'esb-studio__playlist-drop-placeholder';
        placeholder.setAttribute('aria-hidden', 'true');
        placeholder.style.height = `${item.getBoundingClientRect().height}px`;
        list.insertBefore(placeholder, item);
        item.classList.add('esb-studio__setlist-ribbon--dragging');
        list.classList.add('esb-studio__playlist-list--dragging');
        document.body.classList.add('esb-studio__playlist-drag-active');

        if (handle.setPointerCapture) {
            try {
                handle.setPointerCapture(event.pointerId);
            } catch {
                // Some browsers reject capture on non-interactive nodes.
            }
        }

        const onPointerMove = (moveEvent) => {
            if (moveEvent.pointerId !== event.pointerId) {
                return;
            }

            moveEvent.preventDefault();
            repositionDraggedItem(moveEvent.clientY);
        };

        const onPointerEnd = async (endEvent) => {
            if (endEvent.pointerId !== event.pointerId) {
                return;
            }

            document.removeEventListener('pointermove', onPointerMove);
            document.removeEventListener('pointerup', onPointerEnd);
            document.removeEventListener('pointercancel', onPointerEnd);

            await finishPointerDrag();
        };

        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerEnd);
        document.addEventListener('pointercancel', onPointerEnd);
    });
}

export function bootStudioPlaylistOrder() {
    initStudioPlaylistOrder(document.getElementById('playlist-sortable-list'));
}
