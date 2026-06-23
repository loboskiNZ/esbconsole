export function studioChartsLauncher(searchUrl) {
    return {
        query: '',
        results: [],
        open: false,
        loading: false,
        activeIndex: -1,
        debounceTimer: null,

        init() {
            this.$watch('query', () => this.scheduleSearch());
        },

        scheduleSearch() {
            clearTimeout(this.debounceTimer);
            this.debounceTimer = setTimeout(() => this.runSearch(), 180);
        },

        async runSearch() {
            const trimmed = this.query.trim();

            if (trimmed === '') {
                this.results = [];
                this.open = false;
                this.activeIndex = -1;

                return;
            }

            this.loading = true;

            try {
                const response = await fetch(
                    `${searchUrl}?q=${encodeURIComponent(trimmed)}`,
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
                    this.open = false;
                    this.activeIndex = -1;

                    return;
                }

                const payload = await response.json();
                this.results = Array.isArray(payload.results) ? payload.results : [];
                this.open = this.results.length > 0;
                this.activeIndex = this.results.length > 0 ? 0 : -1;
            } finally {
                this.loading = false;
            }
        },

        selectResult(result) {
            if (! result?.url) {
                return;
            }

            window.location.assign(result.url);
        },

        submitSearch() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                this.selectResult(this.results[this.activeIndex]);

                return;
            }

            if (this.results.length === 1) {
                this.selectResult(this.results[0]);
            }
        },

        onSearchKeydown(event) {
            if (! this.open || this.results.length === 0) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    this.submitSearch();
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

                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                this.open = false;
                this.activeIndex = -1;
            }
        },

        resultId(index) {
            return `studio-chart-search-result-${index}`;
        },
    };
}
