import { csrfToken, updatePlaylistSummary } from './studio-playlist-order';

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

function showRemoveFeedback(message, isError = false) {
    const feedback = document.getElementById('playlist-add-feedback');

    if (! feedback) {
        return;
    }

    feedback.textContent = message;
    feedback.hidden = false;
    feedback.classList.toggle('esb-portal__error', isError);
    feedback.classList.toggle('esb-portal__success', ! isError);
    feedback.setAttribute('role', isError ? 'alert' : 'status');
}

export function initStudioPlaylistRemove(root = document) {
    root.querySelectorAll('.esb-studio__playlist-remove-form').forEach((form) => {
        if (form.dataset.removeReady === 'true') {
            return;
        }

        form.dataset.removeReady = 'true';

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const confirmMessage = form.dataset.confirmRemove
                ?? 'Remove this song from this playlist? The song will remain in the library.';

            if (! window.confirm(confirmMessage)) {
                return;
            }

            const item = form.closest('[data-playlist-item-id]');
            const list = document.getElementById('playlist-sortable-list');
            const formData = new FormData(form);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: formData,
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    throw new Error(payload.message ?? 'Unable to remove song from playlist.');
                }

                item?.remove();
                updatePlaylistSummary(payload.summary);

                if (list && list.querySelectorAll('[data-playlist-item-id]').length === 0) {
                    ensureEmptyPlaylistMessage(list);
                }

                showRemoveFeedback(payload.message ?? 'Song removed from playlist.');
            } catch (error) {
                showRemoveFeedback(
                    error instanceof Error ? error.message : 'Unable to remove song from playlist.',
                    true,
                );
            }
        });
    });
}

export function bootStudioPlaylistRemove() {
    initStudioPlaylistRemove(document.getElementById('playlist') ?? document);
}
