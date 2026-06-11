<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Playlist — {{ $show->name }}
            </h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('shows.show', $show) }}" class="text-indigo-600 hover:text-indigo-800">Show Builder</a>
                <a href="{{ route('shows.index') }}" class="text-gray-600 hover:text-gray-800">← Shows</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-6">
                        Band: <strong>{{ $band->name }}</strong>
                    </p>

                    <h3 class="font-semibold mb-3">Current Playlist</h3>

                    @if ($playlistItems->isEmpty())
                        <p class="text-sm text-gray-600 mb-6">This show has no playlist items yet.</p>
                    @else
                        <ol class="space-y-2 mb-6">
                            @foreach ($playlistItems as $index => $item)
                                <li class="flex items-center justify-between gap-4 py-2 border-b border-gray-100">
                                    <div>
                                        <span class="text-sm text-gray-500 mr-2">{{ $item->position }}.</span>
                                        <a href="{{ route('songs.show', $item->song) }}" class="font-medium text-indigo-700 hover:text-indigo-900">
                                            {{ $item->song->song_code }} — {{ $item->song->name }}
                                        </a>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        @if ($index > 0)
                                            @php
                                                $moveUpOrder = $playlistItems->values()->all();
                                                [$moveUpOrder[$index - 1], $moveUpOrder[$index]] = [$moveUpOrder[$index], $moveUpOrder[$index - 1]];
                                            @endphp
                                            <form method="POST" action="{{ route('playlist.reorder', $show) }}">
                                                @csrf
                                                @foreach ($moveUpOrder as $pi)
                                                    <input type="hidden" name="order[]" value="{{ $pi->id }}">
                                                @endforeach
                                                <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800">↑</button>
                                            </form>
                                        @endif
                                        @if ($index < $playlistItems->count() - 1)
                                            @php
                                                $moveDownOrder = $playlistItems->values()->all();
                                                [$moveDownOrder[$index], $moveDownOrder[$index + 1]] = [$moveDownOrder[$index + 1], $moveDownOrder[$index]];
                                            @endphp
                                            <form method="POST" action="{{ route('playlist.reorder', $show) }}">
                                                @csrf
                                                @foreach ($moveDownOrder as $pi)
                                                    <input type="hidden" name="order[]" value="{{ $pi->id }}">
                                                @endforeach
                                                <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800">↓</button>
                                            </form>
                                        @endif
                                        <form method="POST" action="{{ route('playlist.destroy', [$show, $item]) }}" onsubmit="return confirm('Remove this song from the playlist?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:text-red-800">Remove</button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <h3 class="font-semibold mb-3">Add Song to Playlist</h3>

                    @if ($availableSongs->isEmpty())
                        <p class="text-sm text-gray-600">
                            No available songs.
                            <a href="{{ route('songs.create') }}" class="text-indigo-600 hover:text-indigo-800">Create a song</a>
                            first.
                        </p>
                    @else
                        <form method="POST" action="{{ route('playlist.store', $show) }}" class="flex flex-wrap items-end gap-4">
                            @csrf
                            <div>
                                <x-input-label for="song_id" value="Song" />
                                <select id="song_id" name="song_id" class="mt-1 block border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Select a song…</option>
                                    @foreach ($availableSongs as $song)
                                        <option value="{{ $song->id }}" @selected(old('song_id') == $song->id)>
                                            {{ $song->song_code }} — {{ $song->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('song_id')" class="mt-2" />
                            </div>
                            <x-primary-button type="submit">Add to Playlist</x-primary-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
