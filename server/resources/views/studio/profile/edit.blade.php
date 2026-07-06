@extends('layouts.portal')

@section('title', 'Edit Profile — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
    x-data="profileEditor(@js(old('bio', $person->bio ?? '')), @js(old('primary_instrument', $primaryInstrumentSlug)), @js(old('additional_instruments', $additionalInstrumentSlugs)), @js($instrumentOptions))"
@endsection

@section('content')
    <main class="relative z-10 min-h-dvh px-4 py-8 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-2xl">
            <header class="text-center">
                <p class="esb-portal__eyebrow mb-2">
                    <a href="{{ route('studio') }}" class="esb-studio__brand-link">ESB Studio</a>
                </p>
                <div class="esb-studio__breadcrumbs--centered">
                    @include('studio.partials._breadcrumbs', [
                        'breadcrumbs' => [
                            ['label' => 'Studio', 'url' => route('studio')],
                            ['label' => 'Profile'],
                        ],
                    ])
                </div>
                <h1 class="esb-portal__title">Edit profile</h1>
                <p class="esb-onboarding__lead mx-auto mt-4 max-w-lg">
                    Update your musician profile. Legal name, username, and secure fields are managed separately.
                </p>
            </header>

            <form
                class="esb-portal__panel esb-studio__card mt-8 rounded-2xl p-6 sm:p-8"
                method="POST"
                action="{{ route('studio.profile.update') }}"
                enctype="multipart/form-data"
            >
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="esb-portal__error mb-6" role="alert">
                        <p class="font-semibold">Please review the highlighted fields.</p>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-6">
                    <div>
                        <label class="esb-portal__label mb-3 block" for="profile-photo">Profile photo</label>
                        <div class="flex items-center gap-4">
                            @include('studio.profile._portrait', ['person' => $person, 'editorPortrait' => true])
                            <div class="min-w-0 flex-1">
                                <input
                                    id="profile-photo"
                                    name="profile_photo"
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="esb-portal__input"
                                >
                                <p class="esb-studio__card-note mt-2">JPG, PNG, or WebP. High-quality originals up to 25 MB.</p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="profile-stage-name">Stage name</label>
                        <input
                            id="profile-stage-name"
                            name="stage_name"
                            type="text"
                            class="esb-portal__input"
                            value="{{ old('stage_name', $person->artistic_name) }}"
                            required
                        >
                    </div>

                    <div class="esb-studio__readonly-block rounded-xl border border-[var(--esb-line)] p-4">
                        <p class="esb-studio__card-note mb-1">Legal name</p>
                        <p class="esb-studio__card-body">{{ $person->legalName() }}</p>
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="profile-email">Email</label>
                        <input
                            id="profile-email"
                            name="email"
                            type="email"
                            class="esb-portal__input"
                            value="{{ old('email', $person->email) }}"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="profile-telephone">Phone</label>
                        <input
                            id="profile-telephone"
                            name="telephone"
                            type="tel"
                            class="esb-portal__input"
                            value="{{ old('telephone', $person->phone) }}"
                            autocomplete="tel"
                            required
                        >
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="profile-country">Country</label>
                        <input
                            id="profile-country"
                            name="country"
                            type="text"
                            class="esb-portal__input"
                            value="{{ old('country', $person->country) }}"
                            autocomplete="country-name"
                            required
                        >
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="profile-city">City</label>
                        <input
                            id="profile-city"
                            name="city"
                            type="text"
                            class="esb-portal__input"
                            value="{{ old('city', $person->city) }}"
                            autocomplete="address-level2"
                            required
                        >
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <label class="esb-portal__label block">Instruments</label>
                            <button
                                type="button"
                                class="esb-studio__identity-edit-link"
                                @click="instrumentsOpen = !instrumentsOpen"
                                x-text="instrumentsOpen ? 'Hide instruments' : 'Show instruments'"
                            ></button>
                        </div>

                        <p class="esb-studio__card-body" x-show="!instrumentsOpen" x-cloak>
                            <span class="esb-studio__card-note">Primary:</span>
                            <span x-text="primaryInstrumentLabel || 'Not set'"></span>
                            <template x-if="additionalInstrumentLabels.length">
                                <span>
                                    <span class="esb-studio__card-note"> · Additional:</span>
                                    <span x-text="additionalInstrumentLabels.join(', ')"></span>
                                </span>
                            </template>
                        </p>

                        <div x-show="instrumentsOpen" x-cloak>
                            <input type="hidden" name="primary_instrument" :value="primaryInstrumentSlug">

                            <template x-for="slug in additionalInstrumentSlugs" :key="slug">
                                <input type="hidden" name="additional_instruments[]" :value="slug">
                            </template>

                            <template x-if="primaryInstrumentSlug">
                                <div class="esb-onboarding__weapon-summary mb-6 rounded-xl border border-[var(--esb-line)] p-4">
                                    <p class="esb-onboarding__weapon-summary-label">Your arsenal</p>
                                    <p class="esb-onboarding__weapon-primary mt-2">
                                        <span x-text="instrumentName(primaryInstrumentSlug)"></span>
                                        <span class="esb-onboarding__weapon-badge">Primary</span>
                                    </p>
                                    <template x-if="additionalInstrumentSlugs.length > 0">
                                        <div class="mt-3">
                                            <p class="esb-onboarding__weapon-additional-label">Additional</p>
                                            <ul class="esb-onboarding__weapon-additional-list mt-2">
                                                <template x-for="id in additionalInstrumentSlugs" :key="`summary-${id}`">
                                                    <li class="esb-onboarding__weapon-additional-item">
                                                        <span x-text="instrumentName(id)"></span>
                                                        <button
                                                            type="button"
                                                            class="esb-onboarding__weapon-remove"
                                                            @click="removeAdditionalWeapon(id)"
                                                        >
                                                            Remove
                                                        </button>
                                                    </li>
                                                </template>
                                            </ul>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <p class="esb-portal__label mb-3">Primary weapon</p>
                            <div class="esb-onboarding__instrument-grid mb-6">
                                <template x-for="instrument in scaffoldInstruments" :key="instrument.id">
                                    <button
                                        type="button"
                                        class="esb-onboarding__instrument-chip"
                                        :class="primaryInstrumentSlug === instrument.id ? 'esb-onboarding__instrument-chip--primary' : ''"
                                        @click="setPrimaryWeapon(instrument.id)"
                                        x-text="instrument.name"
                                    ></button>
                                </template>
                            </div>

                            <p class="esb-portal__label mb-3">
                                Additional weapons
                                <span class="esb-onboarding__optional">(optional)</span>
                            </p>
                            <div class="esb-onboarding__instrument-grid">
                                <template x-for="instrument in scaffoldInstruments" :key="`add-${instrument.id}`">
                                    <button
                                        type="button"
                                        class="esb-onboarding__instrument-chip"
                                        :class="isAdditionalWeapon(instrument.id) ? 'esb-onboarding__instrument-chip--active' : ''"
                                        :disabled="primaryInstrumentSlug === instrument.id"
                                        @click="toggleAdditionalWeapon(instrument.id)"
                                        x-text="instrument.name"
                                    ></button>
                                </template>
                            </div>

                            <button
                                type="button"
                                class="esb-onboarding__reset mt-5"
                                @click="clearWeapons()"
                            >
                                Start again
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="profile-bio">Bio</label>
                        <textarea
                            id="profile-bio"
                            name="bio"
                            rows="5"
                            class="esb-portal__input min-h-32 resize-y"
                            x-model="bio"
                        >{{ old('bio', $person->bio) }}</textarea>
                        <p class="esb-studio__card-note mt-2">
                            <span x-text="bioWordCount"></span> / 100 words
                        </p>
                    </div>
                </div>

                <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-between">
                    <a href="{{ route('studio') }}" class="esb-portal__button esb-portal__button--secondary text-center">
                        Back to The Studio
                    </a>
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">
                        Save profile details
                    </button>
                </div>
            </form>
        </div>
    </main>
@endsection
