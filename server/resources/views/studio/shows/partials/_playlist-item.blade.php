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
@endphp

<li
    class="esb-studio__playlist-item esb-studio__setlist-ribbon"
    x-data="{ expanded: false }"
    :class="{ 'esb-studio__setlist-ribbon--expanded': expanded }"
>
    <div class="esb-studio__setlist-ribbon-header">
        <span class="esb-studio__setlist-order-badge">{{ $positionLabel }}</span>

        <h3 class="esb-studio__playlist-song-title">{{ $songTitle }}</h3>

        <div class="esb-studio__setlist-ribbon-actions">
            @if ($isDirector && $song !== null)
                <a
                    href="{{ route('songs.edit', ['song' => $song, 'return_to' => $songEditReturnTo]) }}"
                    class="esb-studio__show-pill"
                >Edit</a>
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

                @if ($isDirector)
                    <div class="esb-studio__playlist-actions">
                        <form method="POST" action="{{ route('studio.shows.playlist.move-up', [$show, $item]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="esb-studio__show-pill esb-studio__show-pill--action">Move up</button>
                        </form>
                        <form method="POST" action="{{ route('studio.shows.playlist.move-down', [$show, $item]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="esb-studio__show-pill esb-studio__show-pill--action">Move down</button>
                        </form>
                        <form method="POST" action="{{ route('studio.shows.playlist.archive', [$show, $item]) }}" onsubmit="return confirm('Archive this playlist item?');">
                            @csrf
                            @method('PATCH')
                            <button type="submit" class="esb-studio__show-pill esb-studio__show-pill--action">Archive</button>
                        </form>
                    </div>
                @endif
            </div>

            @if ($showNotesColumn)
                <aside class="esb-studio__playlist-item-notes" aria-label="Playlist notes for {{ $songTitle }}">
                    @if ($isDirector)
                        <form method="POST" action="{{ route('studio.shows.playlist.notes', [$show, $item]) }}" class="esb-studio__playlist-notes-form">
                            @csrf
                            @method('PATCH')
                            <label class="esb-portal__label mb-2 block" for="playlist-notes-{{ $item->id }}">Notes</label>
                            <textarea
                                id="playlist-notes-{{ $item->id }}"
                                name="notes"
                                rows="6"
                                class="esb-portal__input esb-studio__band-textarea esb-studio__playlist-notes-input"
                                placeholder="Cue notes, arrangement reminders, or production notes for this song."
                            >{{ old('notes', $item->notes) }}</textarea>
                            <div class="esb-studio__band-form-actions mt-3">
                                <button type="submit" class="esb-portal__button esb-portal__button--secondary">Save notes</button>
                            </div>
                        </form>
                    @else
                        <div class="esb-studio__playlist-notes-block">
                            <span class="esb-studio__playlist-notes-label">Notes</span>
                            <p class="esb-studio__playlist-notes">{{ $item->notes }}</p>
                        </div>
                    @endif
                </aside>
            @endif
        </div>
    </div>
</li>
