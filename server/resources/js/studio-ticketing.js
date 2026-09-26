export function studioTicketing(tierRows = []) {
    const tiers = Array.isArray(tierRows) && tierRows.length > 0
        ? tierRows.map((tier) => normalizeTier(tier))
        : [blankTier()];

    return {
        tiers,
        addTier() {
            const currency = document.getElementById('ticketing-currency')?.value || '';
            this.tiers.push(blankTier(currency));
        },
        removeTier(index) {
            this.tiers.splice(index, 1);

            if (this.tiers.length === 0) {
                this.tiers.push(blankTier());
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
        private_offer: false,
    };
}

function normalizeTier(tier) {
    return {
        public_id: tier?.public_id ?? '',
        name: tier?.name ?? '',
        amount: tier?.amount ?? '',
        currency: tier?.currency ?? '',
        category: tier?.category ?? '',
        starts_at: tier?.starts_at ?? '',
        ends_at: tier?.ends_at ?? '',
        enabled: tier?.enabled === false || tier?.enabled === 0 || tier?.enabled === '0' ? '0' : '1',
        private_offer: tier?.private_offer === true
            || tier?.private_offer === 1
            || tier?.private_offer === '1'
            || tier?.private_offer === 'true'
            || tier?.private_offer === 'on',
    };
}
