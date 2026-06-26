@php
    $item = $entry['item'];
    $metadata = $entry['metadata'];
    $parts = $entry['instrument_parts'];
    $song = $item->song;
@endphp

<li class="esb-studio__playlist-item">
    <div class="esb-studio__playlist-item-head">
        <h3 class="esb-studio__playlist-song-title">{{ $song?->name ?? 'Song' }}</h3>
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

    <div class="esb-studio__playlist-parts">
        <h4 class="esb-studio__playlist-parts-title">Instrument parts</h4>
        @if ($parts === [])
            <p class="esb-studio__card-body">No instrument parts defined.</p>
        @else
            <ul class="esb-studio__playlist-parts-list">
                @foreach ($parts as $part)
                    <li class="esb-studio__playlist-part-row">
                        <span class="esb-studio__playlist-part-name">{{ $part['name'] }}</span>
                        <span class="esb-studio__playlist-part-chart">
                            @if ($part['has_chart'])
                                ✓ Chart
                            @else
                                —
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if (! $isDirector && $item->notes)
        <p class="esb-studio__playlist-notes">
            <span class="esb-studio__playlist-notes-label">Notes</span>
            {{ $item->notes }}
        </p>
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

        <form method="POST" action="{{ route('studio.shows.playlist.notes', [$show, $item]) }}" class="esb-studio__playlist-notes-form mt-3">
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
