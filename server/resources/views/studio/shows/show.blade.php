@extends('layouts.portal')

@section('title', $show->name.' — Shows — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">{{ $show->name }}</h1>
            <p class="esb-studio__card-body mt-2">Show overview</p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio.shows.index') }}" class="esb-studio__back-link">← Back to Shows</a>
            </div>

            @if (session('show_created'))
                <p class="esb-portal__success mb-4" role="status">
                    Show created.
                </p>
            @endif

            <section class="esb-portal__panel esb-studio__card esb-studio__show-overview">
                <dl class="esb-studio__show-details">
                    <div>
                        <dt>Show name</dt>
                        <dd>{{ $show->name }}</dd>
                    </div>
                    <div>
                        <dt>Date/time</dt>
                        <dd>{{ $show->scheduleLabel() ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Venue / location</dt>
                        <dd>{{ $show->venue_location ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>{{ $show->statusLabel() }}</dd>
                    </div>
                    <div>
                        <dt>Notes</dt>
                        <dd>{{ $show->description ?: '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Playlist</h2>
                <p class="esb-studio__card-body mt-3">Playlist management will appear here in a later phase.</p>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Performances</h2>
                <p class="esb-studio__card-body mt-3">Performance scheduling will appear here in a later phase.</p>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Technical</h2>
                <p class="esb-studio__card-body mt-3">Technical requirements will appear here in a later phase.</p>
            </section>
        </div>

        <footer class="esb-studio__chrome-footer">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                    Log out
                </button>
            </form>
        </footer>
    </main>
@endsection
