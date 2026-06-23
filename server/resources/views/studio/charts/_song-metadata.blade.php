@php($accentColour = $metadata['mood_accent_colour_hex'] ?? \App\Support\StudioSongMetadata::DEFAULT_MOOD_ACCENT)

<section
    class="esb-studio__song-metadata"
    style="--esb-song-mood-accent: {{ $accentColour }};"
    aria-label="Song information"
>
    <div class="esb-studio__song-metadata-head">
        <span class="esb-studio__song-mood-pill">{{ $metadata['mood_label'] }}</span>

        @if ($metadata['bpm'] || $metadata['time_signature'] || $metadata['musical_key'])
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
            </dl>
        @endif
    </div>

    @if ($metadata['director_notes'])
        <div class="esb-studio__song-brief">
            <h3 class="esb-studio__song-brief-title">Song brief</h3>
            <p class="esb-studio__song-brief-body">{{ $metadata['director_notes'] }}</p>
        </div>
    @endif
</section>
