import {
    normalizeUsername,
    validateCountrySelection,
    validateEmail,
    validateInstrumentSlug,
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
    2: ['username', 'password', 'passwordConfirm'],
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

const FIELD_LABELS = {
    username: 'Username',
    password: 'Password',
    passwordConfirm: 'Password confirmation',
    firstName: 'First name',
    middleName: 'Middle name',
    surname: 'Surname',
    stageName: 'Stage name',
    weapons: 'Primary weapon',
    email: 'Email',
    country: 'Country',
    city: 'City',
    telephone: 'Telephone',
};

const SERVER_FIELD_META = {
    username: { step: 2, field: 'username', label: 'Username' },
    password: { step: 2, field: 'password', label: 'Password' },
    password_confirm: { step: 2, field: 'passwordConfirm', label: 'Password confirmation' },
    first_name: { step: 3, field: 'firstName', label: 'First name' },
    middle_name: { step: 3, field: 'middleName', label: 'Middle name' },
    surname: { step: 3, field: 'surname', label: 'Surname' },
    stage_name: { step: 4, field: 'stageName', label: 'Stage name' },
    primary_instrument: { step: 5, field: 'weapons', label: 'Primary weapon' },
    additional_instruments: { step: 5, field: 'weapons', label: 'Additional weapons' },
    email: { step: 6, field: 'email', label: 'Email' },
    country: { step: 6, field: 'country', label: 'Country' },
    country_iso3: { step: 6, field: 'country', label: 'Country' },
    city: { step: 6, field: 'city', label: 'City' },
    telephone: { step: 6, field: 'telephone', label: 'Telephone' },
};

export { SCAFFOLD_INSTRUMENTS, SCAFFOLD_COUNTRIES };

export function portalOnboarding(token = '', backgroundImages = []) {
    return {
        token,
        step: 1,
        fieldIndex: 0,
        introCardIndex: 0,
        introCardsVisible: false,
        fieldError: '',
        submissionErrors: [],
        submitting: false,
        onboardingComplete: false,
        completionMessage: '',
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
            this.submissionErrors = [];

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
            this.submissionErrors = [];
            this.step = 2;
            this.fieldIndex = 0;
        },

        fieldMessage(field) {
            switch (field) {
                case 'username':
                    return validateUsername(this.form.username);
                case 'password':
                    return validatePassword(this.form.password);
                case 'passwordConfirm':
                    return this.form.password !== this.form.passwordConfirm
                        ? 'Passwords must match.'
                        : '';
                case 'firstName':
                    return validateRequired(this.form.firstName, 'First name');
                case 'middleName':
                    return '';
                case 'surname':
                    return validateRequired(this.form.surname, 'Surname');
                case 'stageName':
                    return validateRequired(this.form.stageName, 'Stage name');
                case 'weapons':
                    return validateInstrumentSlug(this.form.primaryInstrument, this.scaffoldInstruments);
                case 'email':
                    return validateEmail(this.form.email);
                case 'country':
                    return validateCountrySelection(this.form.country, this.form.countryIso3);
                case 'telephone':
                    return validateRequired(this.form.telephone, 'Telephone number');
                case 'city':
                    return validateRequired(this.form.city, 'City');
                default:
                    return '';
            }
        },

        validateCurrentField() {
            this.fieldError = '';

            if (this.form.honeypot) {
                return false;
            }

            const field = this.currentField;
            this.fieldError = this.fieldMessage(field);

            if (!this.fieldError && field === 'username') {
                this.form.username = normalizeUsername(this.form.username);
            }

            return this.fieldError === '';
        },

        collectAllFieldErrors() {
            const errors = [];

            for (const [step, fields] of Object.entries(STEP_FIELDS)) {
                for (const field of fields) {
                    const message = this.fieldMessage(field);

                    if (message) {
                        errors.push({
                            step: Number(step),
                            field,
                            label: FIELD_LABELS[field] ?? field,
                            message,
                        });
                    }
                }
            }

            return errors;
        },

        jumpToProblem(problem) {
            if (!problem) {
                return;
            }

            this.step = problem.step;
            const fields = STEP_FIELDS[problem.step] ?? [];
            const fieldIndex = fields.indexOf(problem.field);

            this.fieldIndex = fieldIndex >= 0 ? fieldIndex : 0;
            this.fieldError = problem.message;
            this.onboardingComplete = false;
        },

        applySubmissionErrors(payload) {
            const errors = Object.entries(payload.errors ?? {}).map(([field, messages]) => {
                const meta = SERVER_FIELD_META[field] ?? { step: 8, field: null, label: field };

                return {
                    step: meta.step,
                    field: meta.field,
                    label: meta.label,
                    message: messages[0] ?? 'This field needs attention.',
                };
            });

            if (errors.length === 0) {
                errors.push({
                    step: 8,
                    field: null,
                    label: 'Registration',
                    message: payload.message
                        ?? 'We could not complete your onboarding. Please review your details and try again.',
                });
            }

            this.submissionErrors = errors;
            this.fieldError = errors[0].message;
            this.jumpToProblem(errors[0]);
        },

        async checkUsernameAvailable() {
            const username = normalizeUsername(this.form.username);

            if (!username) {
                return { available: false, message: 'Username is required.' };
            }

            try {
                const response = await fetch('/invite/check-username', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({ username }),
                });

                const payload = await response.json().catch(() => ({}));

                return {
                    available: Boolean(payload.available),
                    message: payload.message ?? (payload.available ? '' : 'That username is already taken.'),
                };
            } catch {
                return {
                    available: true,
                    message: '',
                };
            }
        },

        async continueField() {
            if (!this.validateCurrentField()) {
                return;
            }

            if (this.currentField === 'username') {
                const availability = await this.checkUsernameAvailable();

                if (!availability.available) {
                    this.fieldError = availability.message || 'That username is already taken.';
                    return;
                }
            }

            if (this.fieldIndex < this.currentFields.length - 1) {
                this.fieldIndex += 1;
                return;
            }

            this.advanceStep();
        },

        advanceStep() {
            this.fieldError = '';
            this.submissionErrors = [];
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
            this.submitOnboarding();
        },

        async submitOnboarding() {
            if (this.submitting || this.onboardingComplete) {
                return;
            }

            const clientErrors = this.collectAllFieldErrors();

            if (clientErrors.length > 0) {
                this.submissionErrors = clientErrors;
                this.fieldError = clientErrors[0].message;
                this.jumpToProblem(clientErrors[0]);
                return;
            }

            this.fieldError = '';
            this.submissionErrors = [];
            this.submitting = true;

            try {
                const response = await fetch(`/invite/${this.token}/complete`, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    body: JSON.stringify({
                        username: this.form.username,
                        password: this.form.password,
                        password_confirm: this.form.passwordConfirm,
                        honeypot: this.form.honeypot,
                        first_name: this.form.firstName,
                        middle_name: this.form.middleName,
                        surname: this.form.surname,
                        stage_name: this.form.stageName,
                        primary_instrument: this.form.primaryInstrument,
                        additional_instruments: this.form.additionalInstruments,
                        email: this.form.email,
                        country: this.form.country,
                        country_iso3: this.form.countryIso3,
                        city: this.form.city,
                        telephone: this.form.telephone,
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                if (! response.ok) {
                    this.applySubmissionErrors(payload);
                    return;
                }

                this.onboardingComplete = true;
                this.completionMessage = payload.message
                    ?? 'Your Studio account has been created. Log in to enter The Studio.';
            } catch {
                this.fieldError = 'We could not reach the server. Please try again.';
            } finally {
                this.submitting = false;
            }
        },

        goToLogin() {
            window.location.href = '/?onboarding=complete';
        },
    };
}
