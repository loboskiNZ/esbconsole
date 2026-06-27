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

            @if (session('playlist_updated'))
                <p class="esb-portal__success mb-4" role="status">Playlist updated.</p>
            @endif

            <div class="esb-studio__show-overview-grid {{ $libraryAvailable ? 'esb-studio__show-overview-grid--library' : '' }}">
                <section class="esb-portal__panel esb-studio__card esb-studio__show-section esb-studio__show-overview-card">
                    <h2 class="esb-studio__card-title">Show summary</h2>
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

                @if ($libraryAvailable)
                    <section class="esb-portal__panel esb-studio__card esb-studio__show-section esb-studio__show-overview-card esb-studio__playlist-summary-card">
                        <h2 class="esb-studio__card-title">Playlist summary</h2>
                        <dl class="esb-studio__playlist-summary mt-4">
                            <div>
                                <dt>Songs</dt>
                                <dd>{{ number_format($playlistSummary['song_count']) }}</dd>
                            </div>
                            <div>
                                <dt>Instrument parts</dt>
                                <dd>{{ number_format($playlistSummary['instrument_part_count']) }}</dd>
                            </div>
                            <div>
                                <dt>Charts available</dt>
                                <dd>{{ number_format($playlistSummary['charts_available']) }}</dd>
                            </div>
                            <div>
                                <dt>Charts missing</dt>
                                <dd>{{ number_format($playlistSummary['charts_missing']) }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="esb-portal__panel esb-studio__card esb-studio__show-section esb-studio__show-overview-card esb-studio__instrument-parts-summary-card">
                        <h2 class="esb-studio__card-title">Instrument parts</h2>
                        <p class="esb-studio__card-body mt-2">Distinct parts required across the active playlist.</p>

                        @if ($showInstrumentParts === [])
                            <p class="esb-studio__card-body mt-3">No instrument parts defined.</p>
                        @else
                            <div class="esb-studio__part-pill-grid mt-4">
                                @foreach ($showInstrumentParts as $part)
                                    @include('studio.shows.partials._instrument-part-pill', [
                                        'part' => array_merge($part, [
                                            'has_chart' => false,
                                            'chart_status_label' => '',
                                            'song_id' => 0,
                                            'song_instrument_part_id' => 0,
                                            'chart_id' => null,
                                        ]),
                                        'showChart' => false,
                                        'actionable' => false,
                                    ])
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif
            </div>

            <section id="playlist" class="esb-portal__panel esb-studio__card esb-studio__show-section esb-studio__show-section--playlist mt-4">
                <div class="esb-studio__playlist-head">
                    <h2 class="esb-studio__card-title">Playlist</h2>
                    @if ($isDirector)
                        <p class="esb-studio__card-body">Musical definition for this show production.</p>
                    @endif
                </div>

                @if (! $libraryAvailable)
                    <p class="esb-studio__card-body mt-3">Music library is not available in this environment.</p>
                @elseif ($playlistEntries->isEmpty())
                    <p class="esb-studio__card-body mt-3">No songs on this playlist yet.</p>
                @else
                    <ul class="esb-studio__playlist-list mt-4">
                        @foreach ($playlistEntries as $entry)
                            @include('studio.shows.partials._playlist-item', [
                                'entry' => $entry,
                                'show' => $show,
                                'isDirector' => $isDirector,
                            ])
                        @endforeach
                    </ul>
                @endif

                @if ($isDirector && $libraryAvailable)
                    <form method="POST" action="{{ route('studio.shows.playlist.store', $show) }}" class="esb-studio__playlist-add-form mt-6">
                        @csrf
                        <label class="esb-portal__label mb-2 block" for="playlist-song-id">Add song</label>
                        <div class="esb-studio__playlist-add-row">
                            <select id="playlist-song-id" name="song_id" class="esb-portal__input" required>
                                <option value="" disabled @selected(true)>Select a song</option>
                                @foreach ($selectableSongs as $song)
                                    <option value="{{ $song->id }}">{{ $song->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="esb-portal__button esb-portal__button--primary">Add song</button>
                        </div>
                        @error('song_id')
                            <p class="esb-portal__error mt-2">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
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
