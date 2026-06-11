<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Bulk Create Songs — {{ $band->name }}</h2>
            <a href="{{ route('songs.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Songs</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if ($bulkResult)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 space-y-4">
                        <h3 class="font-semibold text-lg">Bulk Create Result</h3>
                        <p class="text-sm text-gray-600">
                            Created: <strong>{{ $bulkResult['created_count'] }}</strong>
                            · Skipped: <strong>{{ $bulkResult['skipped_count'] }}</strong>
                        </p>

                        @if (! empty($bulkResult['created']))
                            <div>
                                <h4 class="font-medium text-sm mb-2">Created songs</h4>
                                <ul class="text-sm space-y-1">
                                    @foreach ($bulkResult['created'] as $item)
                                        <li>
                                            <a href="{{ route('songs.show', $item['song_id']) }}" class="text-indigo-700 hover:text-indigo-900">
                                                {{ $item['song_code'] }} — {{ $item['name'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if (! empty($bulkResult['skipped']))
                            <div>
                                <h4 class="font-medium text-sm mb-2">Skipped songs</h4>
                                <ul class="text-sm space-y-1 text-gray-700">
                                    @foreach ($bulkResult['skipped'] as $item)
                                        <li><strong>{{ $item['name'] }}</strong> — {{ $item['reason'] }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-6">Paste one song title per line. Song codes are assigned automatically.</p>

                    <form method="POST" action="{{ route('songs.bulk-store') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="song_names" value="Song names" />
                            <textarea
                                id="song_names"
                                name="song_names"
                                rows="10"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                placeholder="Callejero&#10;Sweet Caroline"
                                required
                            >{{ old('song_names') }}</textarea>
                            <x-input-error :messages="$errors->get('song_names')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label value="Instrument parts for all created songs" />
                            @if ($instrumentParts->isEmpty())
                                <p class="text-sm text-gray-600 mt-2">
                                    No instrument parts yet.
                                    <a href="{{ route('instrument-parts.index') }}" class="text-indigo-600 hover:text-indigo-800">Create instrument parts</a>
                                    first.
                                </p>
                            @else
                                <div class="mt-3 space-y-2">
                                    @foreach ($instrumentParts as $part)
                                        <label class="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="instrument_part_ids[]"
                                                value="{{ $part->id }}"
                                                @checked(collect(old('instrument_part_ids', []))->contains($part->id))
                                            >
                                            {{ $part->name }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('instrument_part_ids')" class="mt-2" />
                                <x-input-error :messages="$errors->get('instrument_part_ids.*')" class="mt-2" />
                            @endif
                        </div>

                        <x-primary-button type="submit">Create Songs</x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
