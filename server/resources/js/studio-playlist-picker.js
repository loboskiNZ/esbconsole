import { initStudioPlaylistOrder, updatePlaylistSummary } from './studio-playlist-order';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function ensureSortableList() {
    let list = document.getElementById('playlist-sortable-list');

    if (list) {
        return list;
    }

    const playlistSection = document.getElementById('playlist');

    if (! playlistSection) {
        return null;
    }

    const emptyMessage = document.getElementById('playlist-empty-message');

    if (emptyMessage) {
        emptyMessage.remove();
    }

    let feedback = document.getElementById('playlist-order-feedback');

    if (! feedback) {
        feedback = document.createElement('p');
        feedback.id = 'playlist-order-feedback';
        feedback.className = 'esb-portal__success mt-3 esb-studio__playlist-order-feedback';
        feedback.setAttribute('role', 'status');
        feedback.hidden = true;

        const summary = document.getElementById('playlist-inline-summary');
        summary?.insertAdjacentElement('afterend', feedback);
    }

    list = document.createElement('ul');
    list.id = 'playlist-sortable-list';
    list.className = 'esb-studio__playlist-list mt-4';
    list.dataset.reorderUrl = document.querySelector('.esb-studio__playlist-add')?.dataset.reorderUrl ?? '';

    const addBlock = playlistSection.querySelector('.esb-studio__playlist-add');

    if (addBlock) {
        addBlock.insertAdjacentElement('beforebegin', list);
    } else {
        playlistSection.appendChild(list);
    }

    initStudioPlaylistOrder(list);

    return list;
}

export function studioPlaylistPicker(config) {
    return {
        open: false,
        query: '',
        results: [],
        loading: false,
        activeIndex: -1,
        feedback: '',
        feedbackError: false,
        debounceTimer: null,
        triggerButton: null,
        searchUrl: config.searchUrl,
        addUrl: config.addUrl,

        openPicker() {
            this.triggerButton = document.activeElement;
            this.open = true;
            this.query = '';
            this.results = [];
            this.activeIndex = -1;
            this.feedback = '';
            this.feedbackError = false;

            this.$nextTick(() => {
                this.$refs.searchInput?.focus();
            });
        },

        closePicker() {
            this.open = false;
            this.query = '';
            this.results = [];
            this.activeIndex = -1;

            if (this.triggerButton instanceof HTMLElement) {
                this.triggerButton.focus();
            }
        },

        scheduleSearch() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.runSearch(), 180);
        },

        init() {
            this.$watch('query', () => this.scheduleSearch());
        },

        async runSearch() {
            const trimmed = this.query.trim();

            if (trimmed === '') {
                this.results = [];
                this.activeIndex = -1;

                return;
            }

            this.loading = true;

            try {
                const response = await fetch(
                    `${this.searchUrl}?q=${encodeURIComponent(trimmed)}`,
                    {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (! response.ok) {
                    this.results = [];
                    this.activeIndex = -1;

                    return;
                }

                const payload = await response.json();
                this.results = Array.isArray(payload.results) ? payload.results : [];
                this.activeIndex = this.results.length > 0 ? 0 : -1;
            } finally {
                this.loading = false;
            }
        },

        async selectResult(result) {
            if (! result?.song_id) {
                return;
            }

            if (result.on_playlist) {
                this.feedback = 'That song is already on this playlist.';
                this.feedbackError = true;

                return;
            }

            this.loading = true;
            this.feedback = '';
            this.feedbackError = false;

            try {
                const response = await fetch(this.addUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({ song_id: result.song_id }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.feedback = payload.message ?? 'Unable to add song to playlist.';
                    this.feedbackError = true;

                    return;
                }

                const list = ensureSortableList();

                if (list && payload.html) {
                    list.insertAdjacentHTML('beforeend', payload.html);
                }

                updatePlaylistSummary(payload.summary);
                this.feedback = payload.message ?? 'Song added to playlist.';
                this.feedbackError = false;
                this.closePicker();
            } finally {
                this.loading = false;
            }
        },

        submitSearch() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                this.selectResult(this.results[this.activeIndex]);
            }
        },

        onSearchKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                this.closePicker();

                return;
            }

            if (this.results.length === 0) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                }

                return;
            }

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1);

                return;
            }

            if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.activeIndex = Math.max(this.activeIndex - 1, 0);

                return;
            }

            if (event.key === 'Enter') {
                event.preventDefault();
                this.submitSearch();
            }
        },

        resultId(index) {
            return `playlist-song-picker-result-${index}`;
        },
    };
}
