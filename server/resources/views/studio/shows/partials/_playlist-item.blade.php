@php
    use App\Support\SafeInternalRedirect;

    $item = $entry['item'];
    $metadata = $entry['metadata'];
    $parts = $entry['instrument_parts'];
    $requiredPartCount = $entry['required_part_count'];
    $song = $item->song;
    $songEditReturnTo = $song !== null
        ? app(SafeInternalRedirect::class)->showPlaylistReturnPath($show->id)
        : null;
    $positionLabel = str_pad((string) $item->position, 2, '0', STR_PAD_LEFT);
    $songTitle = $song?->name ?? 'Song';
    $showNotesColumn = $isDirector;
    $detailsId = 'playlist-details-'.$item->id;
    $removeConfirm = 'Remove this song from this playlist? The song will remain in the library.';
@endphp

<li
    class="esb-studio__playlist-item esb-studio__setlist-ribbon"
    data-playlist-item-id="{{ $item->id }}"
    x-data="{ expanded: false }"
    :class="{ 'esb-studio__setlist-ribbon--expanded': expanded }"
>
    <div class="esb-studio__setlist-ribbon-header">
        @if ($isDirector)
            <span
                class="esb-studio__setlist-order-badge esb-studio__setlist-order-badge--draggable"
                data-playlist-order-badge
                data-playlist-drag-handle
                role="button"
                tabindex="0"
                aria-label="Drag to reorder {{ $songTitle }}"
                title="Drag to reorder"
            >{{ $positionLabel }}</span>
        @else
            <span class="esb-studio__setlist-order-badge" data-playlist-order-badge>{{ $positionLabel }}</span>
        @endif

        <div class="esb-studio__playlist-song-head">
            <h3 class="esb-studio__playlist-song-title">{{ $songTitle }}</h3>

            @if ($song !== null && (filled($song->spotify_url) || filled($song->youtube_url)))
                <div class="esb-studio__playlist-song-links">
                    @if (filled($song->spotify_url))
                        <a
                            href="{{ $song->spotify_url }}"
                            class="esb-studio__playlist-song-link esb-studio__playlist-song-link--spotify"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Open Spotify for {{ $songTitle }}"
                        >
                            @include('studio.partials.icons.spotify')
                        </a>
                    @endif

                    @if (filled($song->youtube_url))
                        <a
                            href="{{ $song->youtube_url }}"
                            class="esb-studio__playlist-song-link esb-studio__playlist-song-link--youtube"
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label="Open YouTube for {{ $songTitle }}"
                        >
                            @include('studio.partials.icons.youtube')
                        </a>
                    @endif
                </div>
            @endif
        </div>

        <div class="esb-studio__setlist-ribbon-actions">
            @if ($isDirector && $song !== null)
                <a
                    href="{{ route('songs.edit', ['song' => $song, 'return_to' => $songEditReturnTo]) }}"
                    class="esb-studio__show-pill"
                >Edit</a>

                <button
                    type="button"
                    class="esb-studio__show-pill esb-studio__show-pill--danger"
                    data-playlist-remove
                    data-remove-url="{{ route('studio.shows.playlist.items.destroy', [$show, $item]) }}"
                    data-confirm-remove="{{ $removeConfirm }}"
                >
                    Remove
                </button>
            @endif

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
        class="esb-studio__setlist-ribbon-details"
        hidden
        :hidden="! expanded"
    >
        <div @class([
            'esb-studio__playlist-item-columns',
            'esb-studio__playlist-item-columns--single' => ! $showNotesColumn,
        ])>
            <div class="esb-studio__playlist-item-main">
                <dl class="esb-studio__playlist-metadata">
                    <div>
                        <dt>BPM</dt>
                        <dd>{{ $metadata['bpm'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Key</dt>
                        <dd>{{ $metadata['musical_key'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Time signature</dt>
                        <dd>{{ $metadata['time_signature'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt>Mood</dt>
                        <dd>{{ $metadata['mood_label'] ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($song !== null && filled($song->notes))
                    <div class="esb-studio__playlist-song-notes-block">
                        <p class="esb-studio__playlist-song-notes-label">Song notes</p>
                        <p class="esb-studio__playlist-song-notes">{{ $song->notes }}</p>
                    </div>
                @endif

                @if (filled($item->notes))
                    <div class="esb-studio__playlist-song-notes-block">
                        <p class="esb-studio__playlist-song-notes-label">Notes</p>
                        <p class="esb-studio__playlist-song-notes">{{ $item->notes }}</p>
                    </div>
                @endif

                @if ($isDirector || $parts !== [])
                    <div class="esb-studio__playlist-required-parts">
                        <div class="esb-studio__playlist-required-parts-head">
                            <h4 class="esb-studio__playlist-parts-title">Required parts</h4>
                            <span class="esb-studio__playlist-required-count">{{ $requiredPartCount }}</span>
                        </div>

                        @if ($parts === [])
                            <p class="esb-studio__card-body">No instrument parts defined.</p>
                        @else
                            <div class="esb-studio__part-pill-grid">
                                @foreach ($parts as $part)
                                    @include('studio.shows.partials._instrument-part-pill', [
                                        'part' => $part,
                                        'actionable' => true,
                                        'show' => $show,
                                        'isDirector' => $isDirector,
                                    ])
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            @if ($showNotesColumn)
                <aside class="esb-studio__playlist-item-notes" aria-label="Playlist notes for {{ $songTitle }}">
                    <form method="POST" action="{{ route('studio.shows.playlist.notes', [$show, $item]) }}" class="esb-studio__playlist-notes-form">
                        @csrf
                        @method('PATCH')
                        <label class="esb-portal__label mb-2 block" for="playlist-notes-{{ $item->id }}">Edit playlist notes</label>
                        <textarea
                            id="playlist-notes-{{ $item->id }}"
                            name="notes"
                            rows="6"
                            class="esb-portal__input esb-studio__band-textarea esb-studio__playlist-notes-input"
                            placeholder="Chord charts, cue notes, or arrangement reminders for this song on this show."
                        >{{ old('notes', $item->notes) }}</textarea>
                        <div class="esb-studio__band-form-actions mt-3">
                            <button type="submit" class="esb-portal__button esb-portal__button--secondary">Save notes</button>
                        </div>
                    </form>
                </aside>
            @endif
        </div>
    </div>
</li>
