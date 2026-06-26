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
            <p class="esb-studio__card-body mt-2">Reusable show production</p>
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

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section">
                <h2 class="esb-studio__card-title">Overview</h2>
                <dl class="esb-studio__show-details mt-4">
                    <div>
                        <dt>Show name</dt>
                        <dd>{{ $show->name }}</dd>
                    </div>
                    <div>
                        <dt>Description</dt>
                        <dd>{{ $show->description ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt>Lifecycle state</dt>
                        <dd>{{ $show->statusLabel() }}</dd>
                    </div>
                </dl>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Playlists</h2>
                <p class="esb-studio__card-body mt-3">Playlist management will appear here in a later phase.</p>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Performances</h2>

                @if ($performances->isEmpty())
                    <p class="esb-studio__card-body mt-3">No performances scheduled for this show yet.</p>
                @else
                    <ul class="esb-studio__show-performances-list mt-4">
                        @foreach ($performances as $performance)
                            <li class="esb-studio__show-performances-item">
                                <a href="{{ route('studio.performances.show', $performance) }}" class="esb-studio__show-performances-link">
                                    <span class="esb-studio__show-performances-meta">
                                        {{ $performance->typeLabel() }} · {{ $performance->statusLabel() }}
                                    </span>
                                    <span class="esb-studio__show-performances-details">
                                        {{ $performance->formattedPerformanceDate() }} · {{ $performance->locationNameLabel() }}
                                    </span>
                                    <span class="esb-studio__show-performances-open">Open</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Ableton</h2>
                <p class="esb-studio__card-body mt-3">Ableton show file configuration will appear here in a later phase.</p>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">X32</h2>
                <p class="esb-studio__card-body mt-3">X32 baseline configuration will appear here in a later phase.</p>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Technical</h2>
                <p class="esb-studio__card-body mt-3">Technical requirements will appear here in a later phase.</p>
            </section>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-section mt-4">
                <h2 class="esb-studio__card-title">Files</h2>
                <p class="esb-studio__card-body mt-3">Show files and assets will appear here in a later phase.</p>
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
