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
@endphp

<li class="esb-studio__playlist-item">
    <div class="esb-studio__playlist-item-head">
        <div class="esb-studio__playlist-title-row">
            <h3 class="esb-studio__playlist-song-title">{{ $song?->name ?? 'Song' }}</h3>
            @if ($isDirector && $song !== null)
                <a
                    href="{{ route('songs.edit', ['song' => $song, 'return_to' => $songEditReturnTo]) }}"
                    class="esb-studio__show-pill"
                >Edit</a>
            @endif
        </div>
        <span class="esb-studio__playlist-position">#{{ $item->position }}</span>
    </div>

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

    @if ($item->notes)
        <div class="esb-studio__playlist-notes-block">
            <span class="esb-studio__playlist-notes-label">Notes</span>
            <p class="esb-studio__playlist-notes">{{ $item->notes }}</p>
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

        <form method="POST" action="{{ route('studio.shows.playlist.notes', [$show, $item]) }}" class="esb-studio__playlist-notes-form">
            @csrf
            @method('PATCH')
            <label class="esb-portal__label mb-2 block" for="playlist-notes-{{ $item->id }}">Edit notes</label>
            <textarea
                id="playlist-notes-{{ $item->id }}"
                name="notes"
                rows="3"
                class="esb-portal__input esb-studio__band-textarea"
            >{{ old('notes', $item->notes) }}</textarea>
            <div class="esb-studio__band-form-actions mt-3">
                <button type="submit" class="esb-portal__button esb-portal__button--secondary">Save notes</button>
            </div>
        </form>
    @endif
</li>
