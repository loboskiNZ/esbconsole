@extends('layouts.portal')

@section('title', 'Edit Song — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">Edit Song</h1>
            <p class="esb-studio__card-body mt-2">{{ $song->name }} · {{ $song->song_code }}</p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                @if ($returnTo)
                    <a href="{{ $returnTo }}" class="esb-studio__back-link">
                        @if ($returnTo === app(\App\Support\SafeInternalRedirect::class)->songLibraryReturnPath())
                            ← Back to Song Library
                        @else
                            ← Back to show playlist
                        @endif
                    </a>
                @else
                    <a href="{{ route('studio.charts.show', $song) }}" class="esb-studio__back-link">← Back to charts</a>
                @endif
            </div>

            @if (session('song_created'))
                <p class="esb-portal__success mb-4" role="status">
                    Song created. Continue by adding instrument parts, charts and song assets.
                </p>
            @endif

            @if (session('song_updated'))
                <p class="esb-portal__success mb-4" role="status">Song updated.</p>
            @endif

            @if (session('lyrics_pdf_generated'))
                <p class="esb-portal__success mb-4" role="status">
                    Lyrics PDF saved to Song files. Open it from the Song files panel below.
                </p>
            @endif

            @if (session('lyrics_pdf_error'))
                <p class="esb-portal__error mb-4" role="alert">{{ session('lyrics_pdf_error') }}</p>
            @endif

            @if (session('song_part_added'))
                <p class="esb-portal__success mb-4" role="status">Instrument part added.</p>
            @endif

            @if (session('song_part_removed'))
                <p class="esb-portal__success mb-4" role="status">
                    @if (session('song_part_removed') === 'chart_preserved')
                        Instrument part removed from this song. The linked chart file and record were preserved.
                    @else
                        Instrument part removed from this song.
                    @endif
                </p>
            @endif

            @if (session('song_asset_uploaded'))
                <p class="esb-portal__success mb-4" role="status">Song file uploaded.</p>
            @endif

            @if (session('song_asset_deleted'))
                <p class="esb-portal__success mb-4" role="status">
                    Song file deleted: {{ session('song_asset_deleted') }}.
                </p>
            @endif

            @if ($errors->any())
                <div class="esb-portal__error mb-6" role="alert">
                    <ul class="esb-studio__users-error-list">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="esb-studio__song-edit-layout">
                <div class="esb-studio__song-edit-main">
                    <div class="esb-portal__panel esb-studio__card esb-studio__show-form">
                        <form
                            id="song-edit-form"
                            method="POST"
                            action="{{ route('songs.update', $song) }}"
                        >
                            @csrf
                            @method('PUT')

                            @if ($returnTo)
                                <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            @endif

                            <div class="esb-studio__director-notes-field">
                                <label class="esb-portal__label mb-2 block" for="song-notes">Song notes</label>
                                <p class="esb-studio__card-body mb-3">Canonical song information shown on the show playlist for all musicians.</p>
                                <textarea
                                    id="song-notes"
                                    name="notes"
                                    rows="5"
                                    class="esb-portal__input esb-studio__band-textarea"
                                >{{ old('notes', $song->notes) }}</textarea>
                            </div>

                            <div class="esb-studio__director-notes-field mt-6">
                                <label class="esb-portal__label mb-2 block" for="song-lyrics">Lyrics</label>
                                <p class="esb-studio__card-body mb-3">
                                    Enter tagged lyrics for this song. Use a tag on its own line, such as
                                    <code>{intro}</code>, <code>{verse1}</code>, or <code>{chorus1}</code>, followed by the lyric lines for that section.
                                    Tags become section headings in the generated PDF. Save the song, then click Generate Lyrics PDF.
                                </p>
                                <textarea
                                    id="song-lyrics"
                                    name="lyrics"
                                    rows="14"
                                    class="esb-portal__input esb-studio__band-textarea esb-studio__song-lyrics-input"
                                    spellcheck="false"
                                >{{ old('lyrics', $song->lyrics) }}</textarea>
                            </div>
                        </form>

                        <form
                            method="POST"
                            action="{{ route('songs.lyrics.pdf', $song) }}"
                            class="esb-studio__band-form-actions esb-studio__song-lyrics-generate-form"
                        >
                            @csrf
                            <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                                Generate Lyrics PDF
                            </button>
                        </form>

                            <div class="esb-studio__director-notes-field mt-6">
                                <label class="esb-portal__label mb-2 block" for="song-director-notes">Director notes</label>
                                <p class="esb-studio__card-body mb-3">Internal production guidance. Not shown on the show playlist.</p>
                                <textarea
                                    id="song-director-notes"
                                    form="song-edit-form"
                                    name="director_notes"
                                    rows="5"
                                    class="esb-portal__input esb-studio__band-textarea esb-studio__director-notes-input"
                                >{{ old('director_notes', $song->director_notes) }}</textarea>
                            </div>

                            <div class="esb-studio__band-form-grid mt-6">
                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-name">Song title</label>
                                    <input
                                        id="song-name"
                                        form="song-edit-form"
                                        name="name"
                                        type="text"
                                        class="esb-portal__input"
                                        value="{{ old('name', $song->name) }}"
                                        required
                                    >
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-bpm">BPM</label>
                                    <input
                                        id="song-bpm"
                                        form="song-edit-form"
                                        name="bpm"
                                        type="number"
                                        min="20"
                                        max="300"
                                        class="esb-portal__input"
                                        value="{{ old('bpm', $song->bpm) }}"
                                    >
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-time-signature">Time signature</label>
                                    <select id="song-time-signature" form="song-edit-form" name="time_signature_id" class="esb-portal__input">
                                        <option value="">—</option>
                                        @foreach ($timeSignatures as $timeSignature)
                                            <option value="{{ $timeSignature->id }}" @selected((string) old('time_signature_id', $song->time_signature_id) === (string) $timeSignature->id)>
                                                {{ $timeSignature->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-key">Key</label>
                                    <select id="song-key" form="song-edit-form" name="musical_key_id" class="esb-portal__input">
                                        <option value="">—</option>
                                        @foreach ($musicalKeys as $musicalKey)
                                            <option value="{{ $musicalKey->id }}" @selected((string) old('musical_key_id', $song->musical_key_id) === (string) $musicalKey->id)>
                                                {{ $musicalKey->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-mood">Mood</label>
                                    <select id="song-mood" form="song-edit-form" name="mood_id" class="esb-portal__input">
                                        <option value="">—</option>
                                        @foreach ($moods as $mood)
                                            <option value="{{ $mood->id }}" @selected((string) old('mood_id', $song->mood_id) === (string) $mood->id)>
                                                {{ $mood->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="esb-studio__band-form-actions mt-6 esb-studio__song-edit-save--mobile">
                                <button type="submit" form="song-edit-form" class="esb-portal__button esb-portal__button--primary">
                                    Save song
                                </button>
                            </div>
                    </div>

                    <section class="esb-portal__panel esb-studio__card esb-studio__show-form mt-4">
                        <h2 class="esb-studio__card-title">Instrument parts</h2>
                        <p class="esb-studio__card-body mt-2">Parts required to perform this song.</p>

                        @if ($songInstrumentParts === [])
                            <p class="esb-studio__card-body mt-3">No instrument parts defined.</p>
                        @else
                            <ul class="esb-studio__song-part-list mt-4">
                                @foreach ($songInstrumentParts as $part)
                                    @include('studio.songs.partials._song-instrument-part-row', [
                                        'song' => $song,
                                        'part' => $part,
                                        'returnTo' => $returnTo,
                                    ])
                                @endforeach
                            </ul>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('songs.instrument-parts.store', $song) }}"
                            class="esb-studio__song-parts-add-form mt-6"
                        >
                            @csrf

                            @if ($returnTo)
                                <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            @endif

                            @error('instrument_part')
                                <p class="esb-portal__error mb-4">{{ $message }}</p>
                            @enderror

                            <div class="esb-studio__band-form-grid">
                                <div>
                                    <label class="esb-portal__label mb-2 block" for="instrument-part-id">Add existing part</label>
                                    <select id="instrument-part-id" name="instrument_part_id" class="esb-portal__input">
                                        <option value="">Select a part</option>
                                        @foreach ($attachableInstrumentParts as $instrumentPart)
                                            <option value="{{ $instrumentPart->id }}" @selected((string) old('instrument_part_id') === (string) $instrumentPart->id)>
                                                {{ $instrumentPart->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="new-part-name">Or create new part</label>
                                    <input
                                        id="new-part-name"
                                        name="new_part_name"
                                        type="text"
                                        class="esb-portal__input"
                                        value="{{ old('new_part_name') }}"
                                        placeholder="e.g. Alto Sax"
                                    >
                                </div>
                            </div>

                            <div class="esb-studio__band-form-actions mt-4">
                                <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                                    Add instrument part
                                </button>
                            </div>
                        </form>
                    </section>
                </div>

                <div class="esb-studio__song-edit-aside">
                    <section class="esb-portal__panel esb-studio__card esb-studio__show-form">
                        <h2 class="esb-studio__card-title">External links</h2>
                        <p class="esb-studio__card-body mt-2">Streaming and reference links for this song.</p>
                        <div class="esb-studio__band-form-grid mt-4">
                            <div>
                                <label class="esb-portal__label mb-2 block" for="song-spotify-url">Spotify URL</label>
                                <input
                                    id="song-spotify-url"
                                    form="song-edit-form"
                                    name="spotify_url"
                                    type="url"
                                    class="esb-portal__input"
                                    value="{{ old('spotify_url', $song->spotify_url) }}"
                                    placeholder="https://open.spotify.com/track/..."
                                >
                            </div>
                            <div>
                                <label class="esb-portal__label mb-2 block" for="song-youtube-url">YouTube URL</label>
                                <input
                                    id="song-youtube-url"
                                    form="song-edit-form"
                                    name="youtube_url"
                                    type="url"
                                    class="esb-portal__input"
                                    value="{{ old('youtube_url', $song->youtube_url) }}"
                                    placeholder="https://www.youtube.com/watch?v=..."
                                >
                            </div>
                        </div>

                        <div class="esb-studio__band-form-actions mt-6 esb-studio__song-edit-save--desktop">
                            <button type="submit" form="song-edit-form" class="esb-portal__button esb-portal__button--primary">
                                Save song
                            </button>
                        </div>
                    </section>

                    <section class="esb-portal__panel esb-studio__card esb-studio__show-form mt-4">
                        <h2 class="esb-studio__card-title">Song files</h2>
                        <p class="esb-studio__card-body mt-2">Audio, stems, backing tracks, and MIDI files stored in the music library.</p>

                        @if ($song->assets->isEmpty())
                            <p class="esb-studio__card-body mt-3">No song files uploaded yet.</p>
                        @else
                            <ul class="esb-studio__song-asset-list mt-4">
                                @foreach ($song->assets as $asset)
                                    <li class="esb-studio__song-asset-card">
                                        <div class="esb-studio__song-asset-card-head">
                                            <h3 class="esb-studio__song-asset-label">{{ $asset->label }}</h3>
                                            <span class="esb-studio__song-asset-type">{{ $asset->assetTypeLabel() }}</span>
                                        </div>
                                        <dl class="esb-studio__song-asset-meta">
                                            <div>
                                                <dt>Filename</dt>
                                                <dd>{{ $asset->original_filename }}</dd>
                                            </div>
                                            <div>
                                                <dt>Size</dt>
                                                <dd>{{ $asset->formattedFileSize() }}</dd>
                                            </div>
                                            <div>
                                                <dt>Uploaded</dt>
                                                <dd>{{ $asset->created_at?->format('j M Y') ?? '—' }}</dd>
                                            </div>
                                        </dl>
                                        @if ($asset->notes)
                                            <p class="esb-studio__song-asset-notes">{{ $asset->notes }}</p>
                                        @endif
                                        <div class="esb-studio__song-asset-actions">
                                            <a
                                                href="{{ route('songs.assets.file', [$song, $asset]) }}"
                                                class="esb-studio__show-pill esb-studio__show-pill--action"
                                            >Open / download</a>
                                            <form
                                                method="POST"
                                                action="{{ route('songs.assets.destroy', [$song, $asset]) }}"
                                                class="esb-studio__song-asset-delete-form"
                                                onsubmit="return confirm(@json('Delete '.$asset->displayName().'? This removes the uploaded file permanently.'));"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                @if ($returnTo)
                                                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
                                                @endif

                                                <button type="submit" class="esb-studio__song-part-remove-button">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <form
                            method="POST"
                            action="{{ route('songs.assets.store', $song) }}"
                            enctype="multipart/form-data"
                            class="esb-studio__song-asset-upload-form mt-6"
                        >
                            @csrf

                            @if ($returnTo)
                                <input type="hidden" name="return_to" value="{{ $returnTo }}">
                            @endif

                            @error('file')
                                <p class="esb-portal__error mb-4">{{ $message }}</p>
                            @enderror

                            <div class="esb-studio__band-form-grid">
                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-asset-file">Upload file</label>
                                    <input
                                        id="song-asset-file"
                                        name="file"
                                        type="file"
                                        class="esb-portal__input"
                                        accept=".mp3,.wav,.mid,.midi,audio/mpeg,audio/wav,audio/midi"
                                        required
                                    >
                                    <p class="esb-studio__card-body mt-2">MP3, WAV, or MIDI. Max {{ $songAssetMaxMb }} MB.</p>
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-asset-label">Label</label>
                                    <input
                                        id="song-asset-label"
                                        name="label"
                                        type="text"
                                        class="esb-portal__input"
                                        value="{{ old('label') }}"
                                        placeholder="e.g. Electronic Love no drums"
                                    >
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-asset-type">Asset type</label>
                                    <select id="song-asset-type" name="asset_type" class="esb-portal__input" required>
                                        @foreach ($songAssetTypes as $value => $label)
                                            <option value="{{ $value }}" @selected(old('asset_type') === $value)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="esb-portal__label mb-2 block" for="song-asset-notes">Notes</label>
                                    <textarea
                                        id="song-asset-notes"
                                        name="notes"
                                        rows="3"
                                        class="esb-portal__input esb-studio__band-textarea"
                                    >{{ old('notes') }}</textarea>
                                </div>
                            </div>

                            <div class="esb-studio__band-form-actions mt-4">
                                <button type="submit" class="esb-portal__button esb-portal__button--secondary">
                                    Upload song file
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
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
