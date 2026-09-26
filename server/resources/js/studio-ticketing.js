export function studioTicketing(tierRows = [], defaultOfferIndex = '') {
    return {
        tiers: Array.isArray(tierRows) && tierRows.length > 0 ? tierRows : [blankTier()],
        defaultOfferIndex: String(defaultOfferIndex ?? ''),
        addTier() {
            const currency = document.getElementById('ticketing-currency')?.value || '';
            this.tiers.push(blankTier(currency));
        },
        removeTier(index) {
            this.tiers.splice(index, 1);

            if (this.tiers.length === 0) {
                this.tiers.push(blankTier());
            }

            if (String(this.defaultOfferIndex) === String(index)) {
                this.defaultOfferIndex = '';
            }
        },
    };
}

function blankTier(currency = '') {
    return {
        public_id: '',
        name: '',
        amount: '',
        currency,
        category: '',
        starts_at: '',
        ends_at: '',
        enabled: '1',
    };
}
