@extends('layouts.portal', [
    'backgroundRef' => true,
    'backgroundLoad' => true,
])

@section('title', 'Welcome to the Shadows — Ed and the Shadow Boys')

@section('body-attributes')
    class="esb-portal esb-portal--onboarding antialiased"
    x-data="portalOnboarding(@js($token))"
    x-cloak
@endsection

@section('content')
    <main class="relative z-10 flex min-h-dvh flex-col px-4 py-6 sm:px-6 sm:py-8">
        <p class="esb-onboarding__sr-only">
            Onboarding chapters:
            Welcome to the Shadows,
            Claim Your Identity,
            Your True Name,
            Choose Your Persona,
            Choose Your Weapon,
            Find Your Way Home,
            The Road Ahead,
            Enter the Studio.
        </p>
        <header
            class="mx-auto w-full max-w-3xl text-center transition-opacity duration-700"
            :class="contentVisible ? 'opacity-100' : 'opacity-0'"
        >
            <p class="esb-portal__eyebrow mb-2">Your invitation</p>
            <h1 class="esb-portal__title" x-text="stepTitle"></h1>
            <div class="esb-onboarding__progress mt-5" aria-hidden="true">
                <div class="esb-onboarding__progress-track">
                    <div
                        class="esb-onboarding__progress-fill"
                        :style="`width: ${progressPercent}%`"
                    ></div>
                </div>
                <p class="esb-onboarding__progress-label mt-2">
                    Step <span x-text="step"></span> of 8
                </p>
            </div>
        </header>

        <section class="mx-auto mt-6 w-full max-w-xl flex-1 pb-8 sm:mt-8">
            {{-- Step 1: Introduction --}}
            <div
                x-show="step === 1"
                x-transition:enter="esb-portal-fade-enter"
                x-transition:enter-start="esb-portal-fade-enter-start"
                x-transition:enter-end="esb-portal-fade-enter-end"
                class="text-center"
            >
                <p
                    class="esb-onboarding__lead mx-auto max-w-lg"
                    :class="contentVisible ? 'opacity-100' : 'opacity-0'"
                >
                    You are here because you have been invited to join Ed and the Shadow Boys.
                </p>

                <div
                    class="esb-onboarding__card-stack mt-8"
                    :class="introCardsVisible ? 'opacity-100' : 'opacity-0'"
                >
                    <template x-for="(card, index) in introCards" :key="card.title">
                        <article
                            x-show="introCardIndex === index"
                            x-transition:enter="esb-portal-fade-enter"
                            x-transition:enter-start="esb-portal-fade-enter-start"
                            x-transition:enter-end="esb-portal-fade-enter-end"
                            class="esb-portal__panel esb-onboarding__card rounded-2xl p-6 sm:p-8 text-left"
                        >
                            <p class="esb-onboarding__card-index mb-3" x-text="`0${index + 1}`"></p>
                            <h2 class="esb-onboarding__card-title" x-text="card.title"></h2>
                            <p class="esb-onboarding__card-body mt-3" x-text="card.body"></p>
                        </article>
                    </template>
                </div>

                <div class="mt-8 flex flex-col items-center gap-3">
                    <button
                        type="button"
                        class="esb-portal__button esb-portal__button--primary w-full max-w-xs"
                        @click="introCardIndex < introCards.length - 1 ? advanceIntroCard() : beginJourney()"
                        x-text="introCardIndex < introCards.length - 1 ? 'Continue' : 'Begin Your Journey'"
                    ></button>
                </div>
            </div>

            {{-- Steps 2–6: progressive field steps --}}
            <template x-if="step >= 2 && step <= 6">
                <div
                    x-transition:enter="esb-portal-fade-enter"
                    x-transition:enter-start="esb-portal-fade-enter-start"
                    x-transition:enter-end="esb-portal-fade-enter-end"
                    class="esb-portal__panel esb-onboarding__panel rounded-2xl p-6 sm:p-8"
                >
                    <input
                        type="text"
                        name="website"
                        tabindex="-1"
                        autocomplete="off"
                        class="esb-onboarding__honeypot"
                        x-model="form.honeypot"
                        aria-hidden="true"
                    >

                    <template x-if="step === 3">
                        <p class="esb-onboarding__helper mb-6">
                            Enter your name exactly as it appears on travel documentation.
                            This is used for flights, accommodation, touring logistics, and official administration.
                        </p>
                    </template>

                    <template x-if="step === 4">
                        <p class="esb-onboarding__helper mb-6">
                            What should the world call you? This can be your real name, a nickname, or a persona entirely your own.
                        </p>
                    </template>

                    <template x-if="step === 5">
                        <p class="esb-onboarding__helper mb-4">
                            Every member contributes something unique. Tell us what instrument you play.
                        </p>
                        <p class="esb-onboarding__scaffold-note mb-6">
                            Instrument options below are temporary UI scaffold only — not production data.
                        </p>
                    </template>

                    {{-- Username --}}
                    <div x-show="step === 2 && currentField === 'username'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-username">Choose your username</label>
                        <input
                            id="onboarding-username"
                            type="text"
                            class="esb-portal__input"
                            x-model="form.username"
                            autocomplete="username"
                            maxlength="32"
                            @keydown.enter.prevent="continueField()"
                        >
                        <p class="esb-onboarding__rules mt-3">
                            3–32 characters · letters and numbers only · case insensitive
                        </p>
                    </div>

                    {{-- Password --}}
                    <div x-show="step === 2 && currentField === 'password'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-password">Create your password</label>
                        <input
                            id="onboarding-password"
                            type="password"
                            class="esb-portal__input"
                            x-model="form.password"
                            autocomplete="new-password"
                            maxlength="50"
                            @keydown.enter.prevent="continueField()"
                        >
                        <p class="esb-onboarding__rules mt-3">
                            8–50 characters · uppercase · lowercase · number · symbol
                        </p>
                    </div>

                    {{-- Confirm password --}}
                    <div x-show="step === 2 && currentField === 'passwordConfirm'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-password-confirm">Confirm your password</label>
                        <input
                            id="onboarding-password-confirm"
                            type="password"
                            class="esb-portal__input"
                            x-model="form.passwordConfirm"
                            autocomplete="new-password"
                            maxlength="50"
                            @keydown.enter.prevent="continueField()"
                        >
                    </div>

                    {{-- Human verification --}}
                    <div x-show="step === 2 && currentField === 'humanVerification'">
                        <p class="esb-portal__label mb-4">One last check before we continue</p>
                        <label class="esb-onboarding__checkbox flex items-start gap-3">
                            <input
                                type="checkbox"
                                class="mt-1"
                                x-model="form.humanVerified"
                            >
                            <span>I confirm I am a real person joining Ed and the Shadow Boys.</span>
                        </label>
                        <p class="esb-onboarding__rules mt-3">Human verification placeholder — production CAPTCHA follows in a later phase.</p>
                    </div>

                    {{-- Legal name fields --}}
                    <div x-show="step === 3 && currentField === 'firstName'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-first-name">First name</label>
                        <input id="onboarding-first-name" type="text" class="esb-portal__input" x-model="form.firstName" @keydown.enter.prevent="continueField()">
                    </div>
                    <div x-show="step === 3 && currentField === 'middleName'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-middle-name">Middle name(s)</label>
                        <input id="onboarding-middle-name" type="text" class="esb-portal__input" x-model="form.middleName" @keydown.enter.prevent="continueField()">
                        <p class="esb-onboarding__rules mt-3">Optional — enter multiple middle names together if needed.</p>
                    </div>
                    <div x-show="step === 3 && currentField === 'surname'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-surname">Surname</label>
                        <input id="onboarding-surname" type="text" class="esb-portal__input" x-model="form.surname" @keydown.enter.prevent="continueField()">
                    </div>

                    {{-- Stage name --}}
                    <div x-show="step === 4 && currentField === 'stageName'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-stage-name">Stage name</label>
                        <input id="onboarding-stage-name" type="text" class="esb-portal__input" x-model="form.stageName" @keydown.enter.prevent="continueField()">
                    </div>

                    {{-- Instruments --}}
                    <div x-show="step === 5 && currentField === 'primaryInstrument'">
                        <p class="esb-portal__label mb-4">Primary instrument</p>
                        <div class="esb-onboarding__instrument-grid">
                            <template x-for="instrument in scaffoldInstruments" :key="instrument.id">
                                <button
                                    type="button"
                                    class="esb-onboarding__instrument-chip"
                                    :class="form.primaryInstrument === instrument.id ? 'esb-onboarding__instrument-chip--active' : ''"
                                    @click="form.primaryInstrument = instrument.id"
                                    x-text="instrument.name"
                                ></button>
                            </template>
                        </div>
                    </div>
                    <div x-show="step === 5 && currentField === 'additionalInstruments'">
                        <p class="esb-portal__label mb-4">Additional instruments <span class="esb-onboarding__optional">(optional)</span></p>
                        <div class="esb-onboarding__instrument-grid">
                            <template x-for="instrument in scaffoldInstruments" :key="`add-${instrument.id}`">
                                <button
                                    type="button"
                                    class="esb-onboarding__instrument-chip"
                                    :class="isAdditionalSelected(instrument.id) ? 'esb-onboarding__instrument-chip--active' : ''"
                                    :disabled="form.primaryInstrument === instrument.id"
                                    @click="toggleAdditionalInstrument(instrument.id)"
                                    x-text="instrument.name"
                                ></button>
                            </template>
                        </div>
                    </div>

                    {{-- Contact fields --}}
                    <div x-show="step === 6 && currentField === 'email'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-email">Email address</label>
                        <input id="onboarding-email" type="email" class="esb-portal__input" x-model="form.email" autocomplete="email" @keydown.enter.prevent="continueField()">
                    </div>
                    <div x-show="step === 6 && currentField === 'telephone'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-telephone">Telephone number</label>
                        <input id="onboarding-telephone" type="tel" class="esb-portal__input" x-model="form.telephone" autocomplete="tel" @keydown.enter.prevent="continueField()">
                    </div>
                    <div x-show="step === 6 && currentField === 'city'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-city">City</label>
                        <input id="onboarding-city" type="text" class="esb-portal__input" x-model="form.city" autocomplete="address-level2" @keydown.enter.prevent="continueField()">
                    </div>
                    <div x-show="step === 6 && currentField === 'country'">
                        <label class="esb-portal__label mb-3 block" for="onboarding-country">Country</label>
                        <input id="onboarding-country" type="text" class="esb-portal__input" x-model="form.country" autocomplete="country-name" @keydown.enter.prevent="continueField()">
                    </div>

                    <p
                        x-show="fieldError"
                        x-text="fieldError"
                        class="esb-onboarding__error mt-4"
                        role="alert"
                    ></p>

                    <div class="mt-8">
                        <button
                            type="button"
                            class="esb-portal__button esb-portal__button--primary w-full"
                            @click="continueField()"
                        >
                            Continue
                        </button>
                    </div>
                </div>
            </template>

            {{-- Step 7: The Road Ahead --}}
            <div
                x-show="step === 7"
                x-transition:enter="esb-portal-fade-enter"
                x-transition:enter-start="esb-portal-fade-enter-start"
                x-transition:enter-end="esb-portal-fade-enter-end"
                class="esb-portal__panel esb-onboarding__panel rounded-2xl p-6 sm:p-8 text-center"
            >
                <p class="esb-onboarding__lead">
                    Your journey begins today — but your profile does not need to be complete yet.
                </p>
                <p class="esb-onboarding__helper mt-4">
                    Before touring, payments, and operational activities, you will complete:
                </p>
                <ul class="esb-onboarding__task-list mt-6 text-left">
                    <template x-for="task in futureTasks" :key="task">
                        <li x-text="task"></li>
                    </template>
                </ul>
                <p class="esb-onboarding__helper mt-6">
                    These are not required today. We will guide you when the time comes.
                </p>
                <button
                    type="button"
                    class="esb-portal__button esb-portal__button--primary mt-8 w-full max-w-xs mx-auto"
                    @click="advanceStep()"
                >
                    Continue
                </button>
            </div>

            {{-- Step 8: Enter the Studio --}}
            <div
                x-show="step === 8"
                x-transition:enter="esb-portal-fade-enter"
                x-transition:enter-start="esb-portal-fade-enter-start"
                x-transition:enter-end="esb-portal-fade-enter-end"
                class="esb-portal__panel esb-onboarding__panel esb-onboarding__arrival rounded-2xl p-6 sm:p-10 text-center"
            >
                <p class="esb-onboarding__eyebrow">You have arrived</p>
                <h2 class="esb-onboarding__arrival-title mt-3">The shadows part. The Studio awaits.</h2>
                <p class="esb-onboarding__lead mt-4 mx-auto max-w-md">
                    You are no longer a guest at the threshold. You belong here now.
                </p>
                <button
                    type="button"
                    class="esb-portal__button esb-portal__button--primary mt-8 w-full max-w-xs mx-auto"
                    @click="enterStudio()"
                >
                    Enter the Studio
                </button>
            </div>
        </section>
    </main>
@endsection
