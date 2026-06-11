<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Song</h2>
            <a href="{{ route('songs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Songs</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form method="POST" action="{{ route('songs.store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="name" value="Song name" />
                            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="song_code" value="Song code (SSS)" />
                            <x-text-input id="song_code" name="song_code" type="text" maxlength="3" class="mt-1 block w-full max-w-xs" :value="old('song_code', $suggestedSongCode)" />
                            <p class="text-xs text-gray-500 mt-1">Three digits, e.g. {{ $suggestedSongCode }}. Leave blank to auto-assign.</p>
                            <x-input-error :messages="$errors->get('song_code')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="bpm" value="BPM (optional)" />
                            <x-text-input id="bpm" name="bpm" type="number" min="1" max="999" class="mt-1 block w-full max-w-xs" :value="old('bpm')" />
                            <x-input-error :messages="$errors->get('bpm')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="description" value="Description (optional)" />
                            <textarea id="description" name="description" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                        </div>

                        <x-primary-button>Create Song</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
