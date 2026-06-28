@extends('layouts.portal')

@section('title', 'New Song — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio · Music Library</p>
            <h1 class="esb-portal__title">New Song</h1>
            <p class="esb-studio__card-body mt-2">Song code is assigned automatically when the song is created.</p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('songs.index') }}" class="esb-studio__back-link">← Back to Song Library</a>
            </div>

            <form
                class="esb-portal__panel esb-studio__card esb-studio__show-form"
                method="POST"
                action="{{ route('songs.store') }}"
            >
                @csrf

                @if ($errors->any())
                    <div class="esb-portal__error mb-6" role="alert">
                        <ul class="esb-studio__users-error-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="esb-studio__director-notes-field">
                    <label class="esb-portal__label mb-2 block" for="song-director-notes">Director notes</label>
                    <p class="esb-studio__card-body mb-3">Performance guidance visible to musicians on the song page.</p>
                    <textarea
                        id="song-director-notes"
                        name="director_notes"
                        rows="5"
                        class="esb-portal__input esb-studio__band-textarea esb-studio__director-notes-input"
                    >{{ old('director_notes') }}</textarea>
                </div>

                <div class="esb-studio__band-form-grid mt-6">
                    <div>
                        <label class="esb-portal__label mb-2 block" for="song-name">Song title</label>
                        <input
                            id="song-name"
                            name="name"
                            type="text"
                            class="esb-portal__input"
                            value="{{ old('name') }}"
                            required
                        >
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="song-bpm">BPM</label>
                        <input
                            id="song-bpm"
                            name="bpm"
                            type="number"
                            min="20"
                            max="300"
                            class="esb-portal__input"
                            value="{{ old('bpm') }}"
                        >
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="song-key">Key</label>
                        <select id="song-key" name="musical_key_id" class="esb-portal__input">
                            <option value="">—</option>
                            @foreach ($musicalKeys as $musicalKey)
                                <option value="{{ $musicalKey->id }}" @selected((string) old('musical_key_id') === (string) $musicalKey->id)>
                                    {{ $musicalKey->label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($hasDurationField)
                        <div>
                            <label class="esb-portal__label mb-2 block" for="song-duration">Duration</label>
                            <input
                                id="song-duration"
                                name="duration"
                                type="text"
                                class="esb-portal__input"
                                value="{{ old('duration') }}"
                                placeholder="mm:ss or h:mm:ss"
                            >
                        </div>
                    @endif

                    <div>
                        <label class="esb-portal__label mb-2 block" for="song-spotify-url">Spotify URL</label>
                        <input
                            id="song-spotify-url"
                            name="spotify_url"
                            type="url"
                            class="esb-portal__input"
                            value="{{ old('spotify_url') }}"
                            placeholder="https://open.spotify.com/track/..."
                        >
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="song-youtube-url">YouTube URL</label>
                        <input
                            id="song-youtube-url"
                            name="youtube_url"
                            type="url"
                            class="esb-portal__input"
                            value="{{ old('youtube_url') }}"
                            placeholder="https://www.youtube.com/watch?v=..."
                        >
                    </div>
                </div>

                <div class="esb-studio__band-form-actions mt-6">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">
                        Create song
                    </button>
                </div>
            </form>
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
