import {
    normalizeUsername,
    validateEmail,
    validatePassword,
    validateRequired,
    validateUsername,
} from './onboarding-validators';
import {
    BACKGROUND_ROTATION_MAX_MS,
    BACKGROUND_ROTATION_MIN_MS,
    SCAFFOLD_COUNTRIES,
    SCAFFOLD_INSTRUMENTS,
} from './onboarding-scaffold-data';

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const delay = (ms) =>
    new Promise((resolve) => window.setTimeout(resolve, ms));

const motionDelay = (ms) => (prefersReducedMotion() ? 0 : ms);

const INTRO_CARDS = [
    {
        title: 'Create Your Identity',
        body: 'Claim your place inside the Studio — credentials that belong only to you.',
    },
    {
        title: 'Tell Us Who You Are',
        body: 'The road takes names. We honour yours as it travels.',
    },
    {
        title: 'Choose Your Persona',
        body: 'The world will know you by something true — or something legendary.',
    },
    {
        title: 'Choose Your Weapon',
        body: 'Every shadow needs its sound. Yours may be one voice or many.',
    },
    {
        title: 'Enter the Studio',
        body: 'When you are ready, the door opens.',
    },
];

const STEP_FIELDS = {
    2: ['username', 'password', 'passwordConfirm', 'humanVerification'],
    3: ['firstName', 'middleName', 'surname'],
    4: ['stageName'],
    5: ['weapons'],
    6: ['email', 'country', 'city', 'telephone'],
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

export { SCAFFOLD_INSTRUMENTS, SCAFFOLD_COUNTRIES };

export function portalOnboarding(token = '', backgroundImages = []) {
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
        backgroundImages: backgroundImages.length > 0 ? backgroundImages : [],
        backgroundIndex: 0,
        backgroundRotationTimer: null,
        countryQuery: '',
        countryDropdownOpen: false,
        introCards: INTRO_CARDS,
        scaffoldInstruments: SCAFFOLD_INSTRUMENTS,
        scaffoldCountries: SCAFFOLD_COUNTRIES,
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
            country: '',
            countryIso3: '',
            countryPhoneCode: '',
            city: '',
            telephone: '',
        },

        init() {
            const image = this.$refs.backgroundImage;

            if (image?.complete && image.naturalWidth > 0) {
                this.onBackgroundLoaded();
            }

            this.scheduleBackgroundRotation();
        },

        destroy() {
            if (this.backgroundRotationTimer) {
                window.clearTimeout(this.backgroundRotationTimer);
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

        get canGoBack() {
            if (this.step === 1 && this.introCardIndex === 0) {
                return false;
            }

            return this.step > 1 || this.introCardIndex > 0;
        },

        get filteredCountries() {
            const query = this.countryQuery.trim().toLowerCase();

            if (!query) {
                return this.scaffoldCountries.slice(0, 8);
            }

            return this.scaffoldCountries.filter((country) =>
                country.name.toLowerCase().includes(query)
                || country.iso3.toLowerCase().includes(query),
            );
        },

        get telephoneHint() {
            return this.form.countryPhoneCode
                ? `Include your number — country code ${this.form.countryPhoneCode} is already known.`
                : 'Enter your telephone number.';
        },

        scheduleBackgroundRotation() {
            if (this.backgroundRotationTimer) {
                window.clearTimeout(this.backgroundRotationTimer);
            }

            if (prefersReducedMotion() || this.backgroundImages.length < 2) {
                return;
            }

            const dwell = BACKGROUND_ROTATION_MIN_MS
                + Math.floor(Math.random() * (BACKGROUND_ROTATION_MAX_MS - BACKGROUND_ROTATION_MIN_MS));

            this.backgroundRotationTimer = window.setTimeout(() => {
                this.backgroundIndex = (this.backgroundIndex + 1) % this.backgroundImages.length;
                this.scheduleBackgroundRotation();
            }, dwell);
        },

        isBackgroundActive(index) {
            return this.backgroundIndex === index;
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

        goBack() {
            this.fieldError = '';

            if (this.step === 1 && this.introCardIndex > 0) {
                this.introCardIndex -= 1;
                return;
            }

            if (this.step === 2 && this.fieldIndex > 0) {
                this.fieldIndex -= 1;
                return;
            }

            if (this.step === 2 && this.fieldIndex === 0) {
                this.step = 1;
                this.introCardIndex = this.introCards.length - 1;
                return;
            }

            if (this.step === 8) {
                this.step = 7;
                return;
            }

            if (this.step === 7) {
                this.step = 6;
                this.fieldIndex = (STEP_FIELDS[6]?.length ?? 1) - 1;
                return;
            }

            if (this.fieldIndex > 0) {
                this.fieldIndex -= 1;
                return;
            }

            if (this.step > 2) {
                this.step -= 1;
                const previousFields = STEP_FIELDS[this.step] ?? [];
                this.fieldIndex = Math.max(previousFields.length - 1, 0);
            }
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
                case 'weapons':
                    if (!this.form.primaryInstrument) {
                        this.fieldError = 'Choose your primary weapon.';
                    }
                    break;
                case 'email':
                    this.fieldError = validateEmail(this.form.email);
                    break;
                case 'country':
                    if (!this.form.country || !this.form.countryIso3) {
                        this.fieldError = 'Select your country from the list.';
                    }
                    break;
                case 'telephone':
                    this.fieldError = validateRequired(this.form.telephone, 'Telephone number');
                    break;
                case 'city':
                    this.fieldError = validateRequired(this.form.city, 'City');
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

        setPrimaryWeapon(id) {
            if (this.form.primaryInstrument === id) {
                return;
            }

            this.form.additionalInstruments = this.form.additionalInstruments.filter(
                (item) => item !== id,
            );

            if (this.form.primaryInstrument) {
                const previous = this.form.primaryInstrument;

                if (!this.form.additionalInstruments.includes(previous)) {
                    this.form.additionalInstruments = [...this.form.additionalInstruments, previous];
                }
            }

            this.form.primaryInstrument = id;
            this.form.additionalInstruments = this.form.additionalInstruments.filter(
                (item) => item !== id,
            );
        },

        toggleAdditionalWeapon(id) {
            if (id === this.form.primaryInstrument) {
                return;
            }

            if (this.form.additionalInstruments.includes(id)) {
                this.form.additionalInstruments = this.form.additionalInstruments.filter(
                    (item) => item !== id,
                );
                return;
            }

            this.form.additionalInstruments = [...this.form.additionalInstruments, id];
        },

        removeAdditionalWeapon(id) {
            this.form.additionalInstruments = this.form.additionalInstruments.filter(
                (item) => item !== id,
            );
        },

        clearWeapons() {
            this.form.primaryInstrument = '';
            this.form.additionalInstruments = [];
        },

        isAdditionalWeapon(id) {
            return this.form.additionalInstruments.includes(id);
        },

        instrumentName(id) {
            return this.scaffoldInstruments.find((item) => item.id === id)?.name ?? id;
        },

        onCountryInput() {
            this.countryDropdownOpen = true;
            this.form.country = '';
            this.form.countryIso3 = '';
            this.form.countryPhoneCode = '';
        },

        selectCountry(country) {
            this.form.country = country.name;
            this.form.countryIso3 = country.iso3;
            this.form.countryPhoneCode = country.phoneCode;
            this.countryQuery = country.name;
            this.countryDropdownOpen = false;
        },

        enterStudio() {
            window.location.href = '/studio';
        },
    };
}
