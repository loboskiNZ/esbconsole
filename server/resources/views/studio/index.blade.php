@extends('layouts.portal')

@section('title', 'The Studio — Ed and the Shadow Boys')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="relative z-10 min-h-dvh px-4 py-8 sm:px-6 sm:py-10">
        <div class="mx-auto w-full max-w-5xl">
            <header class="text-center">
                <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
                <h1 class="esb-portal__title">The Studio</h1>
                <p class="esb-onboarding__lead mx-auto mt-4 max-w-2xl">
                    Welcome inside{{ $person?->artistic_name ? ', '.$person->artistic_name : '' }}.
                    This is your member home.
                </p>
            </header>

            @if (session('profile_updated'))
                <p class="esb-portal__success mx-auto mt-6 max-w-3xl text-center" role="status">
                    Your profile details have been saved.
                </p>
            @endif

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                @if ($person)
                    <section class="esb-portal__panel esb-studio__identity-card md:col-span-2">
                        <div class="esb-studio__identity-portrait" aria-hidden="true">
                            <div class="esb-studio__identity-shine"></div>
                            @if ($person->hasProfilePhoto())
                                <img
                                    src="{{ route('studio.profile.photo') }}"
                                    alt=""
                                    class="esb-studio__identity-photo"
                                >
                            @else
                                <div class="esb-studio__identity-placeholder">
                                    <span>{{ $photoInitials }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="esb-studio__identity-body text-center">
                            <p class="esb-portal__eyebrow">My Profile</p>
                            <h2 class="esb-studio__identity-name">{{ $person->artistic_name }}</h2>

                            @if ($person->instrumentSummary() !== '')
                                <p class="esb-studio__identity-instruments">{{ $person->instrumentSummary() }}</p>
                            @endif

                            @if ($person->country)
                                <p class="esb-studio__identity-country">{{ $person->country }}</p>
                            @endif
                        </div>

                        <div class="esb-studio__identity-edit text-center">
                            <a href="{{ route('studio.profile.edit') }}" class="esb-studio__identity-edit-link">
                                Edit
                            </a>
                        </div>
                    </section>
                @endif

                <section class="esb-portal__panel esb-studio__card rounded-2xl p-6">
                    <h2 class="esb-studio__card-title">Welcome</h2>
                    <p class="esb-studio__card-body mt-3">
                        You are signed in as <strong>{{ $user->username }}</strong>.
                        Things you can update are available from your profile when you are ready.
                    </p>
                </section>

                <section class="esb-portal__panel esb-studio__card rounded-2xl p-6">
                    <h2 class="esb-studio__card-title">Information for later</h2>
                    <ul class="esb-studio__list mt-3">
                        <li>Passport information</li>
                        <li>Bank account details</li>
                        <li>Touring information</li>
                    </ul>
                    <p class="esb-studio__card-note mt-4">Coming in later phases.</p>
                </section>

                <section class="esb-portal__panel esb-studio__card rounded-2xl p-6">
                    <h2 class="esb-studio__card-title">Upcoming shows</h2>
                    <p class="esb-studio__card-body mt-3">
                        Your performance calendar will appear here when shows are synced to the portal.
                    </p>
                    <p class="esb-studio__card-note mt-4">Placeholder.</p>
                </section>

                <section class="esb-portal__panel esb-studio__card rounded-2xl p-6">
                    <h2 class="esb-studio__card-title">Band notices</h2>
                    <p class="esb-studio__card-body mt-3">
                        Announcements, rehearsal updates, and touring notes will land here.
                    </p>
                    <p class="esb-studio__card-note mt-4">Placeholder.</p>
                </section>
            </div>

            <div class="esb-studio__footer mt-10 flex flex-col items-center gap-4 text-center sm:flex-row sm:justify-center">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </main>
@endsection
