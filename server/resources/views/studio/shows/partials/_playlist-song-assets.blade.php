@php
    /** @var \App\Models\Library\Song $song */
@endphp

<div class="esb-studio__playlist-song-assets">
    <p class="esb-studio__playlist-song-notes-label">Song files</p>
    <ul class="esb-studio__playlist-asset-list">
        @foreach ($song->assets as $asset)
            @php
                $fileUrl = route('songs.assets.file', [$song, $asset]);
                $displayName = $asset->displayName();
            @endphp
            <li class="esb-studio__playlist-asset">
                <div class="esb-studio__playlist-asset-row">
                    <span class="esb-studio__playlist-asset-icon" aria-hidden="true">
                        {{ $asset->isInlinePlayable() ? '🎵' : '📄' }}
                    </span>
                    <a
                        href="{{ $fileUrl }}"
                        class="esb-studio__playlist-asset-link"
                        download
                    >{{ $displayName }}</a>
                </div>
                @if ($asset->isInlinePlayable())
                    <audio
                        class="esb-studio__playlist-asset-audio"
                        controls
                        preload="none"
                        aria-label="Preview {{ $displayName }}"
                    >
                        <source src="{{ $fileUrl }}" @if ($asset->mime_type) type="{{ $asset->mime_type }}" @endif>
                    </audio>
                @endif
            </li>
        @endforeach
    </ul>
</div>
