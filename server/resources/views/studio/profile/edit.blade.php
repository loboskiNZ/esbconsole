@extends('layouts.portal')

@section('title', 'Edit Profile — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="relative z-10 min-h-dvh px-4 py-8 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-2xl">
            <header class="text-center">
                <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
                <h1 class="esb-portal__title">Edit profile</h1>
                <p class="esb-onboarding__lead mx-auto mt-4 max-w-lg">
                    Update information used for band operations. Legal name, username, and secure fields are managed separately.
                </p>
            </header>

            <form
                class="esb-portal__panel esb-studio__card mt-8 rounded-2xl p-6 sm:p-8"
                method="POST"
                action="{{ route('studio.profile.update') }}"
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
                        <p class="esb-studio__card-note mt-2">Contact an administrator to update legal name details.</p>
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

                    <fieldset>
                        <legend class="esb-portal__label mb-3 block">Primary instrument</legend>
                        <div class="esb-studio__instrument-grid">
                            @foreach ($instruments as $instrument)
                                <label class="esb-studio__instrument-option">
                                    <input
                                        type="radio"
                                        name="primary_instrument"
                                        value="{{ $instrument->slug }}"
                                        @checked(old('primary_instrument', $primaryInstrumentSlug) === $instrument->slug)
                                        required
                                    >
                                    <span>{{ $instrument->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset>
                        <legend class="esb-portal__label mb-3 block">Additional instruments <span class="esb-studio__card-note">(optional)</span></legend>
                        <div class="esb-studio__instrument-grid">
                            @foreach ($instruments as $instrument)
                                <label class="esb-studio__instrument-option">
                                    <input
                                        type="checkbox"
                                        name="additional_instruments[]"
                                        value="{{ $instrument->slug }}"
                                        @checked(in_array($instrument->slug, old('additional_instruments', $additionalInstrumentSlugs), true))
                                    >
                                    <span>{{ $instrument->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="esb-studio__readonly-block rounded-xl border border-[var(--esb-line)] p-4">
                        <p class="esb-portal__label mb-1">Short bio</p>
                        <p class="esb-studio__card-body">Bio editing will be available in a later phase.</p>
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
