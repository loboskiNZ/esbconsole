@extends('layouts.portal')

@section('title', 'Song Library — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">The Studio</h1>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio') }}" class="esb-studio__back-link">← Back to Studio</a>
            </div>

            @if (session('song_archived'))
                <p class="esb-portal__success mb-4" role="status">{{ session('song_archived') }} archived.</p>
            @endif

            @if (session('song_restored'))
                <p class="esb-portal__success mb-4" role="status">{{ session('song_restored') }} restored to the active library.</p>
            @endif

            <section
                class="esb-portal__panel esb-studio__card esb-studio__song-library-panel"
                aria-labelledby="song-library-panel-title"
            >
                <header class="esb-studio__song-library-panel-head">
                    <h2 id="song-library-panel-title" class="esb-studio__card-title">Music Library</h2>
                    <p class="esb-studio__card-body mt-2">Manage the band catalogue — songs, parts, charts, and assets.</p>
                </header>

                <section class="esb-studio__song-library-summary" aria-label="Library summary">
                    <dl class="esb-studio__song-library-summary-grid">
                        <div>
                            <dt>Songs</dt>
                            <dd>{{ number_format($summary['song_count']) }}</dd>
                        </div>
                        <div>
                            <dt>Archived</dt>
                            <dd>{{ number_format($summary['archived_count']) }}</dd>
                        </div>
                        <div>
                            <dt>Charts</dt>
                            <dd>{{ number_format($summary['chart_count']) }}</dd>
                        </div>
                        <div>
                            <dt>Song Assets</dt>
                            <dd>{{ number_format($summary['song_asset_count']) }}</dd>
                        </div>
                    </dl>
                </section>

                <div class="esb-studio__song-library-toolbar">
                    <div class="esb-studio__song-library-toolbar-row">
                        <a href="{{ route('songs.create') }}" class="esb-portal__button esb-portal__button--primary">
                            + New Song
                        </a>

                        <form method="GET" action="{{ route('songs.index') }}" class="esb-studio__song-library-search" role="search">
                            @if ($showArchived)
                                <input type="hidden" name="archived" value="1">
                            @endif
                            @if ($genre !== '')
                                <input type="hidden" name="genre" value="{{ $genre }}">
                            @endif
                            <label class="sr-only" for="song-library-search">Search songs</label>
                            <input
                                id="song-library-search"
                                name="q"
                                type="search"
                                class="esb-portal__input"
                                value="{{ $query }}"
                                placeholder="Search code, title, reference, genre, links…"
                            >
                            <button type="submit" class="esb-portal__button esb-portal__button--secondary">Search</button>
                        </form>
                    </div>

                    <div class="esb-studio__song-library-toolbar-row esb-studio__song-library-toolbar-row--filters">
                        <form method="GET" action="{{ route('songs.index') }}" class="esb-studio__song-library-filter">
                            @if ($query !== '')
                                <input type="hidden" name="q" value="{{ $query }}">
                            @endif
                            @if ($showArchived)
                                <input type="hidden" name="archived" value="1">
                            @endif
                            <label class="esb-portal__label" for="song-library-genre">Filter</label>
                            <select id="song-library-genre" name="genre" class="esb-portal__input" onchange="this.form.submit()">
                                <option value="">All genres</option>
                                @foreach ($genreOptions as $option)
                                    <option value="{{ $option }}" @selected($genre === $option)>{{ $option }}</option>
                                @endforeach
                            </select>
                        </form>

                        <a
                            href="{{ route('songs.index', array_filter([
                                'q' => $query !== '' ? $query : null,
                                'genre' => $genre !== '' ? $genre : null,
                                'archived' => $showArchived ? null : 1,
                            ])) }}"
                            class="esb-studio__show-pill {{ $showArchived ? 'esb-studio__show-pill--active' : '' }}"
                            aria-current="{{ $showArchived ? 'true' : 'false' }}"
                        >
                            {{ $showArchived ? 'Showing archived' : 'Show archived' }}
                        </a>
                    </div>
                </div>

                @if ($songs->isEmpty())
                    <section class="esb-studio__song-library-empty" aria-live="polite">
                        <p class="esb-studio__card-body">
                            @if ($showArchived)
                                No archived songs match your filters.
                            @elseif ($query !== '' || $genre !== '')
                                No active songs match your search.
                            @else
                                No songs in the library yet. Create your first song to get started.
                            @endif
                        </p>
                    </section>
                @else
                    <ul class="esb-studio__song-library-list" role="list">
                        @foreach ($songs as $song)
                            @include('studio.songs.partials._song-library-card', [
                                'song' => $song,
                                'showArchived' => $showArchived,
                                'libraryReturnTo' => $libraryReturnTo,
                                'query' => $query,
                                'genre' => $genre,
                                'showNames' => $showUsageBySongId[$song->id] ?? [],
                            ])
                        @endforeach
                    </ul>
                @endif
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
