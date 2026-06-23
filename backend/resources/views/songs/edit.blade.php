<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-xs uppercase tracking-wide text-gray-500 mb-1">Song Authoring Workspace</p>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit — {{ $song->song_code }}</h2>
            </div>
            <a href="{{ route('songs.show', $song) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to workspace</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 song-workspace-shell">
            <div class="song-workspace-layout song-workspace-layout--edit">
                @include('songs._workspace-nav')

                <form method="POST" action="{{ route('songs.update', $song) }}" class="song-workspace-content space-y-8">
                    @csrf
                    @method('PUT')

                    <section id="overview" class="song-workspace-section">
                        <h3 class="song-workspace-section__title mb-4">Overview</h3>
                        <p class="text-sm text-gray-600 mb-4">Song code: <strong>{{ $song->song_code }}</strong> (fixed)</p>
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="name" value="Song title" />
                                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $song->name)" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="status" value="Status" />
                                <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    @foreach (['draft', 'in_progress', 'ready', 'archived'] as $status)
                                        <option value="{{ $status }}" @selected(old('status', $song->status) === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <section id="musical-metadata" class="song-workspace-section">
                        <h3 class="song-workspace-section__title mb-4">Musical Metadata</h3>
                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <x-input-label for="bpm" value="BPM (optional)" />
                                <x-text-input id="bpm" name="bpm" type="number" min="20" max="300" class="mt-1 block w-full" :value="old('bpm', $song->bpm)" />
                                <x-input-error :messages="$errors->get('bpm')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="count_in" value="Count-in bars (optional)" />
                                <x-text-input id="count_in" name="count_in" type="number" min="0" max="16" class="mt-1 block w-full" :value="old('count_in', $song->count_in)" />
                                <x-input-error :messages="$errors->get('count_in')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="time_signature_id" value="Time signature (optional)" />
                                <select id="time_signature_id" name="time_signature_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">—</option>
                                    @foreach ($timeSignatures as $timeSignature)
                                        <option value="{{ $timeSignature->id }}" @selected((string) old('time_signature_id', $song->time_signature_id) === (string) $timeSignature->id)>
                                            {{ $timeSignature->label }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('time_signature_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="musical_key_id" value="Key (optional)" />
                                <select id="musical_key_id" name="musical_key_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">—</option>
                                    @foreach ($musicalKeys as $musicalKey)
                                        <option value="{{ $musicalKey->id }}" @selected((string) old('musical_key_id', $song->musical_key_id) === (string) $musicalKey->id)>
                                            {{ $musicalKey->label }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('musical_key_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="mood_id" value="Mood (optional)" />
                                <select id="mood_id" name="mood_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                    <option value="">Default (neutral)</option>
                                    @foreach ($moods as $mood)
                                        <option value="{{ $mood->id }}" @selected((string) old('mood_id', $song->mood_id) === (string) $mood->id)>
                                            {{ $mood->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('mood_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="genre" value="Genre (optional)" />
                                <x-text-input id="genre" name="genre" type="text" maxlength="100" class="mt-1 block w-full" :value="old('genre', $song->genre)" />
                                <x-input-error :messages="$errors->get('genre')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="style" value="Style (optional)" />
                                <x-text-input id="style" name="style" type="text" maxlength="100" class="mt-1 block w-full" :value="old('style', $song->style)" />
                                <x-input-error :messages="$errors->get('style')" class="mt-2" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-input-label for="tempo_feel" value="Tempo feel (optional)" />
                                <x-text-input id="tempo_feel" name="tempo_feel" type="text" maxlength="100" class="mt-1 block w-full" :value="old('tempo_feel', $song->tempo_feel)" placeholder="e.g. laid-back groove, driving eighths" />
                                <x-input-error :messages="$errors->get('tempo_feel')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section id="director-brief" class="song-workspace-section">
                        <h3 class="song-workspace-section__title mb-4">Director Brief</h3>
                        <p class="text-xs text-gray-500 mb-4">Musical direction for musicians. Read-only in Cloud Studio.</p>
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="director_notes" value="Song brief (optional)" />
                                <textarea id="director_notes" name="director_notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('director_notes', $song->director_notes) }}</textarea>
                                <x-input-error :messages="$errors->get('director_notes')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="mood_intention" value="Mood / intention (optional)" />
                                <textarea id="mood_intention" name="mood_intention" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('mood_intention', $song->mood_intention) }}</textarea>
                                <x-input-error :messages="$errors->get('mood_intention')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="performance_feel" value="Performance feel (optional)" />
                                <textarea id="performance_feel" name="performance_feel" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('performance_feel', $song->performance_feel) }}</textarea>
                                <x-input-error :messages="$errors->get('performance_feel')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="arrangement_comments" value="Arrangement comments (optional)" />
                                <textarea id="arrangement_comments" name="arrangement_comments" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('arrangement_comments', $song->arrangement_comments) }}</textarea>
                                <x-input-error :messages="$errors->get('arrangement_comments')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section id="references" class="song-workspace-section">
                        <h3 class="song-workspace-section__title mb-4">References</h3>
                        <div class="space-y-4">
                            <div>
                                <x-input-label for="reference_title" value="Reference title (optional)" />
                                <x-text-input id="reference_title" name="reference_title" type="text" class="mt-1 block w-full" :value="old('reference_title', $song->reference_title)" placeholder="e.g. Studio recording, live reference" />
                                <x-input-error :messages="$errors->get('reference_title')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="reference_url" value="Reference URL (optional)" />
                                <x-text-input id="reference_url" name="reference_url" type="url" class="mt-1 block w-full" :value="old('reference_url', $song->reference_url)" />
                                <x-input-error :messages="$errors->get('reference_url')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="reference_notes" value="Reference notes (optional)" />
                                <textarea id="reference_notes" name="reference_notes" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('reference_notes', $song->reference_notes) }}</textarea>
                                <x-input-error :messages="$errors->get('reference_notes')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section id="sync-readiness" class="song-workspace-section song-workspace-section--muted">
                        <h3 class="song-workspace-section__title mb-2">Sync Readiness</h3>
                        <p class="text-sm text-gray-600">Checkout and synchronisation between Live Stage and Cloud Studio will be added in a future phase (ADR-001). Saving this form updates authoring fields only.</p>
                    </section>

                    <div class="flex gap-4">
                        <x-primary-button>Save authoring</x-primary-button>
                        <a href="{{ route('songs.show', $song) }}" class="inline-flex items-center text-sm text-gray-600 hover:text-gray-800">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
