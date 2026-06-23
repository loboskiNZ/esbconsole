export function profileEditor(
    initialBio = '',
    primaryInstrumentSlug = '',
    additionalInstrumentSlugs = [],
    scaffoldInstruments = [],
) {
    return {
        bio: initialBio,
        instrumentsOpen: false,
        primaryInstrumentSlug,
        additionalInstrumentSlugs,
        scaffoldInstruments,

        get bioWordCount() {
            return countWords(this.bio);
        },

        get primaryInstrumentLabel() {
            return this.instrumentName(this.primaryInstrumentSlug);
        },

        get additionalInstrumentLabels() {
            return this.additionalInstrumentSlugs
                .filter((slug) => slug !== this.primaryInstrumentSlug)
                .map((slug) => this.instrumentName(slug));
        },

        instrumentName(id) {
            return this.scaffoldInstruments.find((item) => item.id === id)?.name ?? id;
        },

        setPrimaryWeapon(id) {
            if (this.primaryInstrumentSlug === id) {
                return;
            }

            this.additionalInstrumentSlugs = this.additionalInstrumentSlugs.filter(
                (item) => item !== id,
            );

            if (this.primaryInstrumentSlug) {
                const previous = this.primaryInstrumentSlug;

                if (!this.additionalInstrumentSlugs.includes(previous)) {
                    this.additionalInstrumentSlugs = [...this.additionalInstrumentSlugs, previous];
                }
            }

            this.primaryInstrumentSlug = id;
            this.additionalInstrumentSlugs = this.additionalInstrumentSlugs.filter(
                (item) => item !== id,
            );
        },

        toggleAdditionalWeapon(id) {
            if (id === this.primaryInstrumentSlug) {
                return;
            }

            if (this.additionalInstrumentSlugs.includes(id)) {
                this.additionalInstrumentSlugs = this.additionalInstrumentSlugs.filter(
                    (item) => item !== id,
                );

                return;
            }

            this.additionalInstrumentSlugs = [...this.additionalInstrumentSlugs, id];
        },

        removeAdditionalWeapon(id) {
            this.additionalInstrumentSlugs = this.additionalInstrumentSlugs.filter(
                (item) => item !== id,
            );
        },

        clearWeapons() {
            this.primaryInstrumentSlug = '';
            this.additionalInstrumentSlugs = [];
        },

        isAdditionalWeapon(id) {
            return this.additionalInstrumentSlugs.includes(id);
        },
    };
}

function countWords(value) {
    const trimmed = String(value ?? '').trim();

    if (trimmed === '') {
        return 0;
    }

    return trimmed.split(/\s+/u).length;
}
