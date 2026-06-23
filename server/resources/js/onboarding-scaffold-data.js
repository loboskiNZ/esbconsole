/**
 * Temporary UI scaffold data — not canonical production reference tables (PH048B.1).
 * Future implementation: instrument_reference, countries tables.
 */

export const SCAFFOLD_INSTRUMENTS = [
    { id: 'scaffold-vocals', name: 'Vocals' },
    { id: 'scaffold-electric-guitar', name: 'Electric Guitar' },
    { id: 'scaffold-acoustic-guitar', name: 'Acoustic Guitar' },
    { id: 'scaffold-bass-guitar', name: 'Bass Guitar' },
    { id: 'scaffold-drums', name: 'Drums' },
    { id: 'scaffold-percussion', name: 'Percussion' },
    { id: 'scaffold-keys', name: 'Keys' },
    { id: 'scaffold-accordion', name: 'Accordion' },
    { id: 'scaffold-machines', name: 'Machines' },
    { id: 'scaffold-trumpet', name: 'Trumpet' },
    { id: 'scaffold-trombone', name: 'Trombone' },
    { id: 'scaffold-clarinet', name: 'Clarinet' },
    { id: 'scaffold-alto-sax', name: 'Alto Sax' },
    { id: 'scaffold-tenor-sax', name: 'Tenor Sax' },
    { id: 'scaffold-baritone-sax', name: 'Baritone Sax' },
    { id: 'scaffold-sousaphone', name: 'Sousaphone' },
    { id: 'scaffold-cuatro', name: 'Cuatro' },
];

/** Aligns with future canonical country table: name, iso3, telephone country code. */
export const SCAFFOLD_COUNTRIES = [
    { name: 'New Zealand', iso3: 'NZL', phoneCode: '+64' },
    { name: 'New Caledonia', iso3: 'NCL', phoneCode: '+687' },
    { name: 'Australia', iso3: 'AUS', phoneCode: '+61' },
    { name: 'United States', iso3: 'USA', phoneCode: '+1' },
    { name: 'United Kingdom', iso3: 'GBR', phoneCode: '+44' },
    { name: 'Canada', iso3: 'CAN', phoneCode: '+1' },
    { name: 'France', iso3: 'FRA', phoneCode: '+33' },
    { name: 'Germany', iso3: 'DEU', phoneCode: '+49' },
    { name: 'Japan', iso3: 'JPN', phoneCode: '+81' },
    { name: 'Mexico', iso3: 'MEX', phoneCode: '+52' },
    { name: 'Brazil', iso3: 'BRA', phoneCode: '+55' },
    { name: 'Spain', iso3: 'ESP', phoneCode: '+34' },
    { name: 'Italy', iso3: 'ITA', phoneCode: '+39' },
    { name: 'Netherlands', iso3: 'NLD', phoneCode: '+31' },
    { name: 'Ireland', iso3: 'IRL', phoneCode: '+353' },
    { name: 'Fiji', iso3: 'FJI', phoneCode: '+679' },
    { name: 'Samoa', iso3: 'WSM', phoneCode: '+685' },
    { name: 'Tonga', iso3: 'TON', phoneCode: '+676' },
];

/** Background rotation interval (ms) — meaningful dwell before slow fade. */
export const BACKGROUND_ROTATION_MIN_MS = 20_000;
export const BACKGROUND_ROTATION_MAX_MS = 30_000;
