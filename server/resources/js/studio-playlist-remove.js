import { csrfToken, updatePlaylistSummary } from './studio-playlist-order';

function showPlaylistActionFeedback(message, isError = false) {
    const feedback = document.getElementById('playlist-action-feedback');

    if (! feedback) {
        return;
    }

    feedback.textContent = message;
    feedback.hidden = false;
    feedback.classList.toggle('esb-portal__error', isError);
    feedback.classList.toggle('esb-portal__success', ! isError);
    feedback.setAttribute('role', isError ? 'alert' : 'status');
}

function renumberPlaylistBadges(list) {
    if (! list) {
        return;
    }

    list.querySelectorAll('[data-playlist-item-id]').forEach((element, index) => {
        const badge = element.querySelector('[data-playlist-order-badge]');

        if (badge) {
            badge.textContent = String(index + 1).padStart(2, '0');
        }
    });
}

function ensureEmptyPlaylistMessage(list) {
    const playlistSection = document.getElementById('playlist');

    if (! playlistSection || document.getElementById('playlist-empty-message')) {
        return;
    }

    const message = document.createElement('p');
    message.id = 'playlist-empty-message';
    message.className = 'esb-studio__card-body mt-3';
    message.textContent = 'No songs on this playlist yet.';

    const summary = document.getElementById('playlist-inline-summary');
    summary?.insertAdjacentElement('afterend', message);

    list?.remove();

    document.getElementById('playlist-order-feedback')?.remove();
}

async function removePlaylistItem(button) {
    const removeUrl = button.dataset.removeUrl ?? '';
    const confirmMessage = button.dataset.confirmRemove
        ?? 'Remove this song from this playlist? The song will remain in the library.';

    if (! removeUrl || ! window.confirm(confirmMessage)) {
        return;
    }

    const item = button.closest('[data-playlist-item-id]');
    const list = document.getElementById('playlist-sortable-list');

    button.disabled = true;

    try {
        const response = await fetch(removeUrl, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        const payload = await response.json().catch(() => ({}));

        if (! response.ok) {
            throw new Error(payload.message ?? 'Unable to remove song from playlist.');
        }

        item?.remove();
        updatePlaylistSummary(payload.summary);
        renumberPlaylistBadges(list);

        if (list && list.querySelectorAll('[data-playlist-item-id]').length === 0) {
            ensureEmptyPlaylistMessage(list);
        }

        showPlaylistActionFeedback(payload.message ?? 'Song removed from playlist.');
    } catch (error) {
        button.disabled = false;
        showPlaylistActionFeedback(
            error instanceof Error ? error.message : 'Unable to remove song from playlist.',
            true,
        );
    }
}

export function initStudioPlaylistRemove(root = document) {
    const playlist = root instanceof HTMLElement && root.id === 'playlist'
        ? root
        : root.getElementById?.('playlist') ?? document.getElementById('playlist');

    if (! playlist || playlist.dataset.removeReady === 'true') {
        return;
    }

    playlist.dataset.removeReady = 'true';

    playlist.addEventListener('click', (event) => {
        const button = event.target.closest('[data-playlist-remove]');

        if (! button || ! playlist.contains(button)) {
            return;
        }

        event.preventDefault();
        removePlaylistItem(button);
    });
}

export function bootStudioPlaylistRemove() {
    initStudioPlaylistRemove(document.getElementById('playlist') ?? document);
}
