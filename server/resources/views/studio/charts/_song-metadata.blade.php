@php($accentColour = $metadata['mood_accent_colour_hex'] ?? \App\Support\StudioSongMetadata::DEFAULT_MOOD_ACCENT)

<section
    class="esb-studio__song-metadata"
    style="--esb-song-mood-accent: {{ $accentColour }};"
    aria-label="Song information"
>
    <div class="esb-studio__song-metadata-head">
        <span class="esb-studio__song-mood-pill">{{ $metadata['mood_label'] }}</span>

        @if ($metadata['bpm'] || $metadata['time_signature'] || $metadata['musical_key'] || $metadata['genre'] || $metadata['style'] || $metadata['tempo_feel'] || $metadata['count_in'])
            <dl class="esb-studio__song-metadata-facts">
                @if ($metadata['bpm'])
                    <div>
                        <dt class="sr-only">BPM</dt>
                        <dd>{{ $metadata['bpm'] }} BPM</dd>
                    </div>
                @endif
                @if ($metadata['time_signature'])
                    <div>
                        <dt class="sr-only">Time signature</dt>
                        <dd>{{ $metadata['time_signature'] }}</dd>
                    </div>
                @endif
                @if ($metadata['musical_key'])
                    <div>
                        <dt class="sr-only">Key</dt>
                        <dd>{{ $metadata['musical_key'] }}</dd>
                    </div>
                @endif
                @if ($metadata['genre'])
                    <div>
                        <dt class="sr-only">Genre</dt>
                        <dd>{{ $metadata['genre'] }}</dd>
                    </div>
                @endif
                @if ($metadata['style'])
                    <div>
                        <dt class="sr-only">Style</dt>
                        <dd>{{ $metadata['style'] }}</dd>
                    </div>
                @endif
                @if ($metadata['tempo_feel'])
                    <div>
                        <dt class="sr-only">Tempo feel</dt>
                        <dd>{{ $metadata['tempo_feel'] }}</dd>
                    </div>
                @endif
                @if ($metadata['count_in'])
                    <div>
                        <dt class="sr-only">Count-in</dt>
                        <dd>{{ $metadata['count_in'] }}-bar count-in</dd>
                    </div>
                @endif
            </dl>
        @endif
    </div>

    @if ($metadata['has_brief'])
        <div class="esb-studio__song-brief">
            <h3 class="esb-studio__song-brief-title">Song brief</h3>
            @if ($metadata['director_notes'])
                <p class="esb-studio__song-brief-body">{{ $metadata['director_notes'] }}</p>
            @endif
            @if ($metadata['mood_intention'])
                <p class="esb-studio__song-brief-sub">
                    <span class="esb-studio__song-brief-label">Mood / intention</span>
                    {{ $metadata['mood_intention'] }}
                </p>
            @endif
            @if ($metadata['performance_feel'])
                <p class="esb-studio__song-brief-sub">
                    <span class="esb-studio__song-brief-label">Performance feel</span>
                    {{ $metadata['performance_feel'] }}
                </p>
            @endif
            @if ($metadata['arrangement_comments'])
                <p class="esb-studio__song-brief-sub">
                    <span class="esb-studio__song-brief-label">Arrangement</span>
                    {{ $metadata['arrangement_comments'] }}
                </p>
            @endif
        </div>
    @endif

    @if ($metadata['reference_title'] || $metadata['reference_url'])
        <div class="esb-studio__song-reference">
            <h3 class="esb-studio__song-brief-title">Reference</h3>
            @if ($metadata['reference_title'])
                <p class="esb-studio__song-brief-body">{{ $metadata['reference_title'] }}</p>
            @endif
            @if ($metadata['reference_url'])
                <a href="{{ $metadata['reference_url'] }}" class="esb-studio__song-reference-link" target="_blank" rel="noopener noreferrer">Open reference</a>
            @endif
        </div>
    @endif
</section>
