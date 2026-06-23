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
                    Welcome inside{{ $user?->person?->artistic_name ? ', '.$user->person->artistic_name : '' }}.
                    This is your member home.
                </p>
            </header>

            <div class="mt-10 grid gap-6 md:grid-cols-2">
                <section class="esb-portal__panel esb-studio__card rounded-2xl p-6">
                    <h2 class="esb-studio__card-title">Welcome</h2>
                    <p class="esb-studio__card-body mt-3">
                        You are signed in as <strong>{{ $user->username }}</strong>.
                        Profile completion tasks remain available when you are ready.
                    </p>
                </section>

                <section class="esb-portal__panel esb-studio__card rounded-2xl p-6">
                    <h2 class="esb-studio__card-title">Profile tasks</h2>
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
