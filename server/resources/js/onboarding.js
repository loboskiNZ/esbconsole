import {
    normalizeUsername,
    validateEmail,
    validatePassword,
    validateRequired,
    validateUsername,
} from './onboarding-validators';

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const delay = (ms) =>
    new Promise((resolve) => window.setTimeout(resolve, ms));

const motionDelay = (ms) => (prefersReducedMotion() ? 0 : ms);

/** Temporary UI scaffold — not canonical instrument_reference data (PH048B). */
export const SCAFFOLD_INSTRUMENTS = [
    { id: 'scaffold-electric-guitar', name: 'Electric Guitar' },
    { id: 'scaffold-acoustic-guitar', name: 'Acoustic Guitar' },
    { id: 'scaffold-bass', name: 'Bass Guitar' },
    { id: 'scaffold-drums', name: 'Drums' },
    { id: 'scaffold-keys', name: 'Keys' },
    { id: 'scaffold-vocals', name: 'Vocals' },
    { id: 'scaffold-trumpet', name: 'Trumpet' },
    { id: 'scaffold-saxophone', name: 'Saxophone' },
    { id: 'scaffold-trombone', name: 'Trombone' },
    { id: 'scaffold-percussion', name: 'Percussion' },
];

const INTRO_CARDS = [
    {
        title: 'Create Your Identity',
        body: 'You will create the credentials used to access the ESB Studio.',
    },
    {
        title: 'Tell Us Who You Are',
        body: 'We collect legal identity information required for travel, accommodation, touring and administration.',
    },
    {
        title: 'Choose Your Persona',
        body: 'Tell us what the world should call you.',
    },
    {
        title: 'Choose Your Weapon',
        body: 'Tell us what instrument you play.',
    },
    {
        title: 'Enter the Studio',
        body: 'Once complete, you will gain access to the ESB Studio.',
    },
];

const STEP_FIELDS = {
    2: ['username', 'password', 'passwordConfirm', 'humanVerification'],
    3: ['firstName', 'middleName', 'surname'],
    4: ['stageName'],
    5: ['primaryInstrument', 'additionalInstruments'],
    6: ['email', 'telephone', 'city', 'country'],
};

const STEP_TITLES = {
    1: 'Welcome to the Shadows',
    2: 'Claim Your Identity',
    3: 'Your True Name',
    4: 'Choose Your Persona',
    5: 'Choose Your Weapon',
    6: 'Find Your Way Home',
    7: 'The Road Ahead',
    8: 'Enter the Studio',
};

const FUTURE_TASKS = [
    'Passport information',
    'Bank account details',
    'Artist image',
    'Quick bio',
    'Touring information',
];

export function portalOnboarding(token = '') {
    return {
        token,
        step: 1,
        fieldIndex: 0,
        introCardIndex: 0,
        introCardsVisible: false,
        fieldError: '',
        bgLoaded: false,
        bgVisible: false,
        overlayVisible: false,
        logoVisible: false,
        contentVisible: false,
        introCards: INTRO_CARDS,
        scaffoldInstruments: SCAFFOLD_INSTRUMENTS,
        futureTasks: FUTURE_TASKS,
        form: {
            honeypot: '',
            username: '',
            password: '',
            passwordConfirm: '',
            humanVerified: false,
            firstName: '',
            middleName: '',
            surname: '',
            stageName: '',
            primaryInstrument: '',
            additionalInstruments: [],
            email: '',
            telephone: '',
            city: '',
            country: '',
        },

        init() {
            const image = this.$refs.backgroundImage;

            if (image?.complete && image.naturalWidth > 0) {
                this.onBackgroundLoaded();
            }
        },

        get stepTitle() {
            return STEP_TITLES[this.step] ?? '';
        },

        get currentFields() {
            return STEP_FIELDS[this.step] ?? [];
        },

        get currentField() {
            return this.currentFields[this.fieldIndex] ?? null;
        },

        get progressPercent() {
            return Math.round((this.step / 8) * 100);
        },

        async onBackgroundLoaded() {
            if (this.bgLoaded) {
                return;
            }

            this.bgLoaded = true;
            this.bgVisible = true;

            await delay(motionDelay(900));
            this.overlayVisible = true;

            await delay(motionDelay(700));
            this.logoVisible = true;

            await delay(motionDelay(1000));
            this.contentVisible = true;

            await delay(motionDelay(600));
            this.introCardsVisible = true;
        },

        advanceIntroCard() {
            if (this.introCardIndex < this.introCards.length - 1) {
                this.introCardIndex += 1;
                return;
            }

            this.beginJourney();
        },

        beginJourney() {
            this.fieldError = '';
            this.step = 2;
            this.fieldIndex = 0;
        },

        validateCurrentField() {
            this.fieldError = '';

            if (this.form.honeypot) {
                return false;
            }

            const field = this.currentField;

            switch (field) {
                case 'username':
                    this.fieldError = validateUsername(this.form.username);

                    if (!this.fieldError) {
                        this.form.username = normalizeUsername(this.form.username);
                    }
                    break;
                case 'password':
                    this.fieldError = validatePassword(this.form.password);
                    break;
                case 'passwordConfirm':
                    if (this.form.password !== this.form.passwordConfirm) {
                        this.fieldError = 'Passwords must match.';
                    }
                    break;
                case 'humanVerification':
                    if (!this.form.humanVerified) {
                        this.fieldError = 'Please confirm you are a real person.';
                    }
                    break;
                case 'firstName':
                    this.fieldError = validateRequired(this.form.firstName, 'First name');
                    break;
                case 'middleName':
                    break;
                case 'surname':
                    this.fieldError = validateRequired(this.form.surname, 'Surname');
                    break;
                case 'stageName':
                    this.fieldError = validateRequired(this.form.stageName, 'Stage name');
                    break;
                case 'primaryInstrument':
                    if (!this.form.primaryInstrument) {
                        this.fieldError = 'Choose your primary instrument.';
                    }
                    break;
                case 'additionalInstruments':
                    break;
                case 'email':
                    this.fieldError = validateEmail(this.form.email);
                    break;
                case 'telephone':
                    this.fieldError = validateRequired(this.form.telephone, 'Telephone number');
                    break;
                case 'city':
                    this.fieldError = validateRequired(this.form.city, 'City');
                    break;
                case 'country':
                    this.fieldError = validateRequired(this.form.country, 'Country');
                    break;
                default:
                    break;
            }

            return this.fieldError === '';
        },

        continueField() {
            if (!this.validateCurrentField()) {
                return;
            }

            if (this.fieldIndex < this.currentFields.length - 1) {
                this.fieldIndex += 1;
                return;
            }

            this.advanceStep();
        },

        advanceStep() {
            this.fieldError = '';
            this.fieldIndex = 0;

            if (this.step < 8) {
                this.step += 1;
            }
        },

        toggleAdditionalInstrument(id) {
            const selected = this.form.additionalInstruments;
            const index = selected.indexOf(id);

            if (index === -1) {
                this.form.additionalInstruments = [...selected, id];
                return;
            }

            this.form.additionalInstruments = selected.filter((item) => item !== id);
        },

        isAdditionalSelected(id) {
            return this.form.additionalInstruments.includes(id);
        },

        instrumentName(id) {
            return this.scaffoldInstruments.find((item) => item.id === id)?.name ?? id;
        },

        enterStudio() {
            window.location.href = '/studio';
        },
    };
}
