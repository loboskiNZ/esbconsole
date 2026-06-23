const INSTRUMENT_LABELS = {
    'scaffold-vocals': 'Vocals',
    'scaffold-electric-guitar': 'Electric Guitar',
    'scaffold-acoustic-guitar': 'Acoustic Guitar',
    'scaffold-bass-guitar': 'Bass Guitar',
    'scaffold-drums': 'Drums',
    'scaffold-percussion': 'Percussion',
    'scaffold-keys': 'Keys',
    'scaffold-accordion': 'Accordion',
    'scaffold-machines': 'Machines',
    'scaffold-trumpet': 'Trumpet',
    'scaffold-trombone': 'Trombone',
    'scaffold-clarinet': 'Clarinet',
    'scaffold-alto-sax': 'Alto Sax',
    'scaffold-tenor-sax': 'Tenor Sax',
    'scaffold-baritone-sax': 'Baritone Sax',
    'scaffold-sousaphone': 'Sousaphone',
    'scaffold-cuatro': 'Cuatro',
};

function countWords(value) {
    const trimmed = String(value ?? '').trim();

    if (trimmed === '') {
        return 0;
    }

    return trimmed.split(/\s+/u).length;
}

export function profileEditor(initialBio = '', primaryInstrumentSlug = '', additionalInstrumentSlugs = []) {
    return {
        bio: initialBio,
        instrumentsOpen: false,
        primaryInstrumentSlug,
        additionalInstrumentSlugs,

        get bioWordCount() {
            return countWords(this.bio);
        },

        get primaryInstrumentLabel() {
            return INSTRUMENT_LABELS[this.primaryInstrumentSlug] ?? this.primaryInstrumentSlug;
        },

        get additionalInstrumentLabels() {
            return this.additionalInstrumentSlugs
                .filter((slug) => slug !== this.primaryInstrumentSlug)
                .map((slug) => INSTRUMENT_LABELS[slug] ?? slug);
        },
    };
}
