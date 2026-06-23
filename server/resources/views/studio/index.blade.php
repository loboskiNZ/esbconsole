@extends('layouts.portal')

@section('title', 'The Studio — Ed and the Shadow Boys')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="relative z-10 min-h-dvh px-4 py-6 sm:px-6 sm:py-8">
        <div class="mx-auto w-full max-w-6xl">
            <header class="esb-studio__page-header">
                <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
                <h1 class="esb-portal__title">The Studio</h1>
            </header>

            @if (session('profile_updated'))
                <p class="esb-portal__success mt-4" role="status">
                    Your profile details have been saved.
                </p>
            @endif

            <div class="esb-studio__layout mt-6 sm:mt-8">
                @if ($person)
                    <aside class="esb-studio__sidebar">
                        @include('studio.profile._identity-widget', ['person' => $person])
                    </aside>
                @endif

                <div class="esb-studio__workspace">
                    <section class="esb-portal__panel esb-studio__workspace-intro">
                        <h2 class="esb-studio__card-title">Welcome</h2>
                        <p class="esb-studio__card-body mt-2">
                            Welcome inside{{ $person?->artistic_name ? ', '.$person->artistic_name : '' }}.
                            This is your member home.
                        </p>
                        <p class="esb-studio__card-body mt-3">
                            You are signed in as <strong>{{ $user->username }}</strong>.
                            Things you can update are available from your profile when you are ready.
                        </p>
                    </section>

                    <div class="esb-studio__workspace-grid">
                        <section class="esb-portal__panel esb-studio__card">
                            <h2 class="esb-studio__card-title">Information for later</h2>
                            <ul class="esb-studio__list mt-3">
                                <li>Passport information</li>
                                <li>Bank account details</li>
                                <li>Touring information</li>
                            </ul>
                            <p class="esb-studio__card-note mt-4">Coming in later phases.</p>
                        </section>

                        <section class="esb-portal__panel esb-studio__card">
                            <h2 class="esb-studio__card-title">Upcoming shows</h2>
                            <p class="esb-studio__card-body mt-3">
                                Your performance calendar will appear here when shows are synced to the portal.
                            </p>
                            <p class="esb-studio__card-note mt-4">Placeholder.</p>
                        </section>

                        <section class="esb-portal__panel esb-studio__card">
                            <h2 class="esb-studio__card-title">Band notices</h2>
                            <p class="esb-studio__card-body mt-3">
                                Announcements, rehearsal updates, and touring notes will land here.
                            </p>
                            <p class="esb-studio__card-note mt-4">Placeholder.</p>
                        </section>

                        <section class="esb-portal__panel esb-studio__card">
                            <h2 class="esb-studio__card-title">Studio modules</h2>
                            <p class="esb-studio__card-body mt-3">
                                Songs, charts, performances, messages, rehearsal, and media will open here as they arrive.
                            </p>
                            <p class="esb-studio__card-note mt-4">Placeholder.</p>
                        </section>
                    </div>
                </div>
            </div>

            <div class="esb-studio__footer mt-8 flex flex-col items-center gap-4 text-center sm:flex-row sm:justify-end">
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
