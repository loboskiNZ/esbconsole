function parseIsoDate(iso) {
    if (!iso) {
        return null;
    }

    const [year, month, day] = iso.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function isoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function startOfWeek(date) {
    const copy = new Date(date);
    const day = copy.getDay();
    copy.setDate(copy.getDate() - day);

    return copy;
}

function addDays(date, days) {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);

    return copy;
}

function monthMatrix(focusDate) {
    const first = new Date(focusDate.getFullYear(), focusDate.getMonth(), 1);
    const start = startOfWeek(first);
    const weeks = [];

    for (let week = 0; week < 6; week += 1) {
        const days = [];

        for (let day = 0; day < 7; day += 1) {
            days.push(addDays(start, week * 7 + day));
        }

        weeks.push(days);
    }

    return weeks;
}

function rsvpResponseFromCard(card) {
    switch (card?.rsvp) {
        case 'available':
            return 'yes';
        case 'unavailable':
            return 'no';
        case 'maybe':
            return 'maybe';
        default:
            return 'yes';
    }
}

export function studioSchedule(cards = [], hasMusicianLink = true) {
    return {
        cards,
        hasMusicianLink,
        modalOpen: false,
        activeCard: null,
        response: 'yes',
        notes: '',

        openRsvp(card) {
            this.activeCard = card;
            this.response = rsvpResponseFromCard(card);
            this.notes = '';
            this.modalOpen = true;
        },

        closeRsvp() {
            this.modalOpen = false;
            this.activeCard = null;
        },

        showNotesField() {
            return this.response === 'no';
        },
    };
}

export function studioCalendar(cards = [], upcomingCards = [], hasMusicianLink = true) {
    const todayIso = isoDate(new Date());

    return {
        cards,
        upcomingCards,
        hasMusicianLink,
        view: window.matchMedia('(max-width: 767px)').matches ? 'week' : 'month',
        focusDate: new Date(),
        modalOpen: false,
        activeCard: null,
        response: 'yes',
        notes: '',

        setView(nextView) {
            this.view = nextView;
        },

        openRsvp(card) {
            this.activeCard = card;
            this.response = rsvpResponseFromCard(card);
            this.notes = '';
            this.modalOpen = true;
        },

        closeRsvp() {
            this.modalOpen = false;
            this.activeCard = null;
        },

        showNotesField() {
            return this.response === 'no';
        },

        previousPeriod() {
            if (this.view === 'month') {
                this.focusDate = new Date(this.focusDate.getFullYear(), this.focusDate.getMonth() - 1, 1);
            } else if (this.view === 'week') {
                this.focusDate = addDays(this.focusDate, -7);
            }
        },

        nextPeriod() {
            if (this.view === 'month') {
                this.focusDate = new Date(this.focusDate.getFullYear(), this.focusDate.getMonth() + 1, 1);
            } else if (this.view === 'week') {
                this.focusDate = addDays(this.focusDate, 7);
            }
        },

        periodLabel() {
            if (this.view === 'week') {
                const start = startOfWeek(this.focusDate);
                const end = addDays(start, 6);

                return `${start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })}`;
            }

            return this.focusDate.toLocaleDateString(undefined, { month: 'long', year: 'numeric' });
        },

        monthWeeks() {
            return monthMatrix(this.focusDate);
        },

        weekDays() {
            const start = startOfWeek(this.focusDate);
            const days = [];

            for (let index = 0; index < 7; index += 1) {
                days.push(addDays(start, index));
            }

            return days;
        },

        performancesForDate(date) {
            const iso = isoDate(date);

            return this.cards.filter((card) => card.date_iso === iso);
        },

        listItems() {
            return [...this.cards]
                .filter((card) => card.date_iso >= todayIso)
                .sort((left, right) => left.date_iso.localeCompare(right.date_iso) || left.time.localeCompare(right.time));
        },

        isSameMonth(date) {
            return date.getMonth() === this.focusDate.getMonth() && date.getFullYear() === this.focusDate.getFullYear();
        },

        isToday(date) {
            return isoDate(date) === todayIso;
        },

        dayLabel(date) {
            return date.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric' });
        },
    };
}
