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
                    <a href="{{ $returnTo }}" class="esb-studio__back-link">← Back to show playlist</a>
                @else
                    <a href="{{ route('studio.charts.show', $song) }}" class="esb-studio__back-link">← Back to charts</a>
                @endif
            </div>

            @if (session('song_updated'))
                <p class="esb-portal__success mb-4" role="status">Song updated.</p>
            @endif

            @if (session('song_part_added'))
                <p class="esb-portal__success mb-4" role="status">Instrument part added.</p>
            @endif

            <form
                class="esb-portal__panel esb-studio__card esb-studio__show-form"
                method="POST"
                action="{{ route('songs.update', $song) }}"
            >
                @csrf
                @method('PUT')

                @if ($returnTo)
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">
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

                <div class="esb-studio__band-form-grid">
                    <div>
                        <label class="esb-portal__label mb-2 block" for="song-name">Song title</label>
                        <input
                            id="song-name"
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
                        <select id="song-time-signature" name="time_signature_id" class="esb-portal__input">
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
                        <select id="song-key" name="musical_key_id" class="esb-portal__input">
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
                        <select id="song-mood" name="mood_id" class="esb-portal__input">
                            <option value="">—</option>
                            @foreach ($moods as $mood)
                                <option value="{{ $mood->id }}" @selected((string) old('mood_id', $song->mood_id) === (string) $mood->id)>
                                    {{ $mood->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="esb-portal__label mb-2 block" for="song-director-notes">Director notes</label>
                        <textarea
                            id="song-director-notes"
                            name="director_notes"
                            rows="4"
                            class="esb-portal__input esb-studio__band-textarea"
                        >{{ old('director_notes', $song->director_notes) }}</textarea>
                    </div>
                </div>

                <div class="esb-studio__band-form-actions mt-6">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">
                        Save song
                    </button>
                </div>
            </form>

            <section class="esb-portal__panel esb-studio__card esb-studio__show-form mt-4">
                <h2 class="esb-studio__card-title">Instrument parts</h2>
                <p class="esb-studio__card-body mt-2">Parts required to perform this song.</p>

                @if ($songInstrumentParts === [])
                    <p class="esb-studio__card-body mt-3">No instrument parts defined.</p>
                @else
                    <div class="esb-studio__part-pill-grid mt-4">
                        @foreach ($songInstrumentParts as $part)
                            @include('studio.shows.partials._instrument-part-pill', [
                                'part' => array_merge($part, [
                                    'song_id' => $song->id,
                                    'instrument_part_id' => null,
                                    'song_instrument_part_id' => $part['song_instrument_part_id'],
                                ]),
                                'showChart' => true,
                                'actionable' => false,
                            ])
                        @endforeach
                    </div>
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
