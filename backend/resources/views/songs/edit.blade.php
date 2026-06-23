<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Edit Song</h2>
            <a href="{{ route('songs.show', $song) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Song</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-4">Song code: <strong>{{ $song->song_code }}</strong> (cannot be changed here)</p>

                    <form method="POST" action="{{ route('songs.update', $song) }}" class="space-y-6">
                        @csrf
                        @method('PUT')

                        <div>
                            <x-input-label for="name" value="Song name" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $song->name)" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div class="grid gap-6 sm:grid-cols-2">
                            <div>
                                <x-input-label for="bpm" value="BPM (optional)" />
                                <x-text-input id="bpm" name="bpm" type="number" min="20" max="300" class="mt-1 block w-full" :value="old('bpm', $song->bpm)" />
                                <x-input-error :messages="$errors->get('bpm')" class="mt-2" />
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
                        </div>

                        <div>
                            <x-input-label for="description" value="Description (optional)" />
                            <textarea id="description" name="description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description', $song->description) }}</textarea>
                        </div>

                        <div>
                            <x-input-label for="director_notes" value="Director notes / song brief (optional)" />
                            <textarea id="director_notes" name="director_notes" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('director_notes', $song->director_notes) }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">Musical direction and preparation notes for musicians. Read-only in Studio.</p>
                            <x-input-error :messages="$errors->get('director_notes')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="status" value="Status" />
                            <select id="status" name="status" class="mt-1 block border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach (['draft', 'in_progress', 'ready', 'archived'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $song->status) === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <x-primary-button>Save Changes</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
