@extends('layouts.portal')

@section('title', 'The Studio — Ed and the Shadow Boys')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    @php
        $person = $user->person;
        $primaryInstrument = $person?->primaryInstrument();
        $additionalInstruments = $person?->additionalInstruments() ?? collect();
    @endphp

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
                <section class="esb-portal__panel esb-studio__card rounded-2xl p-6 md:col-span-2">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="esb-studio__card-title">My Profile</h2>
                            <p class="esb-studio__card-body mt-2">
                                Profile details used for band operations.
                            </p>
                        </div>
                        @if ($person)
                            <a href="{{ route('studio.profile.edit') }}" class="esb-portal__button esb-portal__button--secondary shrink-0">
                                Edit profile
                            </a>
                        @endif
                    </div>

                    @if ($person)
                        <dl class="esb-studio__profile-grid mt-6">
                            <div>
                                <dt>Stage name</dt>
                                <dd>{{ $person->artistic_name }}</dd>
                            </div>
                            <div>
                                <dt>Legal name</dt>
                                <dd>{{ $person->legalName() }}</dd>
                            </div>
                            <div>
                                <dt>Email</dt>
                                <dd>{{ $person->email }}</dd>
                            </div>
                            <div>
                                <dt>Phone</dt>
                                <dd>{{ $person->phone }}</dd>
                            </div>
                            <div>
                                <dt>City</dt>
                                <dd>{{ $person->city }}</dd>
                            </div>
                            <div>
                                <dt>Country</dt>
                                <dd>{{ $person->country }}</dd>
                            </div>
                            <div>
                                <dt>Primary instrument</dt>
                                <dd>{{ $primaryInstrument?->name ?? '—' }}</dd>
                            </div>
                            <div class="md:col-span-2">
                                <dt>Additional instruments</dt>
                                <dd>
                                    @if ($additionalInstruments->isEmpty())
                                        —
                                    @else
                                        {{ $additionalInstruments->pluck('name')->join(', ') }}
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    @else
                        <p class="esb-studio__card-body mt-6">
                            No profile is linked to this account yet.
                        </p>
                    @endif
                </section>

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
                        <li>Artist image</li>
                        <li>Quick bio</li>
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
