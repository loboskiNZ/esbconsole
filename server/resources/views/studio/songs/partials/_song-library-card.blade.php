@php
    use App\Services\StudioSongLibraryService;

    $durationLabel = null;
    if (isset($song->duration_seconds) && $song->duration_seconds !== null) {
        $durationLabel = app(StudioSongLibraryService::class)->formatDurationLabel((int) $song->duration_seconds);
    }

    $partCount = $song->songInstrumentParts->count();
    $chartCount = $song->charts->count();
    $assetCount = $song->assets->count();
    $keyLabel = $song->musicalKey?->label;
    $detailsId = 'song-library-details-'.$song->id;
    $editUrl = route('songs.edit', ['song' => $song, 'return_to' => $libraryReturnTo]);
    $archiveConfirm = 'Archive this song? It will be hidden from the active library and playlist picker. Existing playlists and all charts, assets, and parts are preserved.';
    $restoreConfirm = 'Restore this song to the active library?';
    $statusClass = match ($song->status) {
        \App\Models\Library\Song::STATUS_READY => 'esb-studio__song-status-pill--ready',
        \App\Models\Library\Song::STATUS_ARCHIVED => 'esb-studio__song-status-pill--archived',
        \App\Models\Library\Song::STATUS_IN_PROGRESS => 'esb-studio__song-status-pill--progress',
        default => 'esb-studio__song-status-pill--draft',
    };
    $usedInLabel = $showNames !== [] ? implode(', ', $showNames) : 'Unknown';
@endphp

<li
    class="esb-studio__song-library-item-card"
    x-data="{ expanded: false }"
    :class="{ 'esb-studio__song-library-item-card--expanded': expanded }"
>
    <div class="esb-studio__song-library-item-header">
        <span class="esb-studio__setlist-order-badge esb-studio__song-library-code-badge">{{ $song->song_code }}</span>

        <div class="esb-studio__song-library-head-main">
            <div class="esb-studio__song-library-title-row">
                <h3 class="esb-studio__song-library-title">{{ $song->name }}</h3>
                <span class="esb-studio__song-status-pill {{ $statusClass }}">{{ $song->statusLabel() }}</span>
            </div>
            <ul class="esb-studio__song-library-facts" aria-label="Song summary">
                @if ($song->bpm)
                    <li>{{ $song->bpm }} BPM</li>
                @endif
                @if ($keyLabel)
                    <li>{{ $keyLabel }}</li>
                @endif
                <li>{{ $partCount }} {{ $partCount === 1 ? 'part' : 'parts' }}</li>
                <li>{{ $chartCount }} {{ $chartCount === 1 ? 'chart' : 'charts' }}</li>
                <li>{{ $assetCount }} {{ $assetCount === 1 ? 'asset' : 'assets' }}</li>
            </ul>
        </div>

        <div class="esb-studio__song-library-item-actions">
            <a href="{{ $editUrl }}" class="esb-studio__show-pill">Edit</a>

            <button
                type="button"
                class="esb-studio__setlist-toggle"
                @click="expanded = ! expanded"
                aria-expanded="false"
                :aria-expanded="expanded ? 'true' : 'false'"
                aria-controls="{{ $detailsId }}"
            >
                <span class="esb-studio__setlist-toggle-icon" aria-hidden="true" x-show="! expanded">+</span>
                <span class="esb-studio__setlist-toggle-icon" aria-hidden="true" x-show="expanded" x-cloak>−</span>
                <span class="esb-studio__setlist-toggle-label">
                    <span x-show="! expanded">Expand song details</span>
                    <span x-show="expanded" x-cloak>Collapse song details</span>
                </span>
            </button>
        </div>
    </div>

    <div
        id="{{ $detailsId }}"
        class="esb-studio__song-library-details"
        x-show="expanded"
        x-cloak
        hidden
        :hidden="! expanded"
    >
        <dl class="esb-studio__song-metadata-facts">
            @if ($durationLabel)
                <div>
                    <dt>Duration</dt>
                    <dd>{{ $durationLabel }}</dd>
                </div>
            @endif
            @if ($song->genre)
                <div>
                    <dt>Genre</dt>
                    <dd>{{ $song->genre }}</dd>
                </div>
            @endif
            @if ($song->reference_title)
                <div>
                    <dt>Reference</dt>
                    <dd>{{ $song->reference_title }}</dd>
                </div>
            @endif
            <div>
                <dt>Used in</dt>
                <dd>{{ $usedInLabel }}</dd>
            </div>
            <div>
                <dt>Last played</dt>
                <dd>Not available</dd>
            </div>
            @if ($song->director_notes)
                <div class="esb-studio__song-library-notes">
                    <dt>Director notes</dt>
                    <dd>{{ $song->director_notes }}</dd>
                </div>
            @endif
        </dl>

        @if ($song->spotify_url || $song->youtube_url || $song->reference_url)
            <div class="esb-studio__song-reference mt-4">
                <h4 class="esb-studio__song-brief-label">Links</h4>
                <ul class="esb-studio__song-library-links">
                    @if ($song->spotify_url)
                        <li><a href="{{ $song->spotify_url }}" class="esb-studio__song-reference-link" target="_blank" rel="noopener noreferrer">Spotify</a></li>
                    @endif
                    @if ($song->youtube_url)
                        <li><a href="{{ $song->youtube_url }}" class="esb-studio__song-reference-link" target="_blank" rel="noopener noreferrer">YouTube</a></li>
                    @endif
                    @if ($song->reference_url)
                        <li><a href="{{ $song->reference_url }}" class="esb-studio__song-reference-link" target="_blank" rel="noopener noreferrer">Reference</a></li>
                    @endif
                </ul>
            </div>
        @endif

        <div class="esb-studio__song-library-expanded-grid mt-4">
            <section aria-label="Instrument parts">
                <h4 class="esb-studio__song-brief-label">Instrument parts</h4>
                @if ($partCount === 0)
                    <p class="esb-studio__card-body">No instrument parts defined.</p>
                @else
                    <ul class="esb-studio__song-library-tag-list">
                        @foreach ($song->songInstrumentParts as $songPart)
                            <li>{{ $songPart->instrumentPart?->name ?? 'Part' }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section aria-label="Charts">
                <h4 class="esb-studio__song-brief-label">Charts</h4>
                @if ($chartCount === 0)
                    <p class="esb-studio__card-body">No charts uploaded.</p>
                @else
                    <ul class="esb-studio__song-library-tag-list">
                        @foreach ($song->charts->take(8) as $chart)
                            <li>{{ $chart->title }}</li>
                        @endforeach
                        @if ($chartCount > 8)
                            <li class="esb-studio__song-library-more">+{{ $chartCount - 8 }} more</li>
                        @endif
                    </ul>
                @endif
            </section>

            <section aria-label="Song assets">
                <h4 class="esb-studio__song-brief-label">Song assets</h4>
                @if ($assetCount === 0)
                    <p class="esb-studio__card-body">No song files uploaded.</p>
                @else
                    <ul class="esb-studio__song-library-tag-list">
                        @foreach ($song->assets->take(6) as $asset)
                            <li>{{ $asset->label }}</li>
                        @endforeach
                        @if ($assetCount > 6)
                            <li class="esb-studio__song-library-more">+{{ $assetCount - 6 }} more</li>
                        @endif
                    </ul>
                @endif
            </section>
        </div>

        <div class="esb-studio__song-library-card-actions mt-4">
            @if ($showArchived)
                <form
                    method="POST"
                    action="{{ route('songs.restore', $song) }}"
                    onsubmit="return confirm(@json($restoreConfirm))"
                >
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                        Restore song
                    </button>
                </form>
            @else
                <form
                    method="POST"
                    action="{{ route('songs.archive', $song) }}"
                    onsubmit="return confirm(@json($archiveConfirm))"
                >
                    @csrf
                    @method('PATCH')
                    @if ($query !== '')
                        <input type="hidden" name="q" value="{{ $query }}">
                    @endif
                    @if ($genre !== '')
                        <input type="hidden" name="genre" value="{{ $genre }}">
                    @endif
                    <button type="submit" class="esb-studio__show-pill esb-studio__show-pill--danger">
                        Archive song
                    </button>
                </form>
            @endif
        </div>
    </div>
</li>
