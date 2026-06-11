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
                        <div
                            class="space-y-4 mb-6"
                            x-data="{
                                selected: [],
                                reorderMessage: '',
                                reorderError: '',
                                get count() { return this.selected.length; },
                                confirmRemove() {
                                    if (this.count === 0) {
                                        return false;
                                    }

                                    const label = this.count === 1 ? 'song' : 'songs';

                                    return confirm('Remove ' + this.count + ' selected ' + label + ' from this playlist?');
                                },
                            }"
                            @playlist-reorder-success="reorderMessage = $event.detail.message; reorderError = ''"
                            @playlist-reorder-error="reorderError = $event.detail.message; reorderMessage = ''"
                        >
                            <form
                                id="playlist-bulk-remove-form"
                                method="POST"
                                action="{{ route('playlist.bulk-destroy', $show) }}"
                                class="flex flex-wrap items-center gap-4 text-sm"
                                @submit="return confirmRemove()"
                            >
                                @csrf
                                @method('DELETE')

                                <p class="text-gray-700">
                                    Selected: <strong x-text="count"></strong>
                                    <span x-text="count === 1 ? 'song' : 'songs'"></span>
                                </p>
                                <button
                                    type="submit"
                                    class="text-red-600 hover:text-red-800 disabled:opacity-50 disabled:cursor-not-allowed"
                                    :disabled="count === 0"
                                >
                                    Remove
                                </button>
                            </form>

                            <p
                                x-show="reorderMessage"
                                x-cloak
                                class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2"
                                x-text="reorderMessage"
                            ></p>
                            <p
                                x-show="reorderError"
                                x-cloak
                                class="text-sm text-red-700 bg-red-50 border border-red-200 rounded px-3 py-2"
                                x-text="reorderError"
                            ></p>

                            <p class="hidden md:block text-xs text-gray-500">
                                Drag the handle to reorder. Select multiple songs to move them together.
                            </p>

                            <ol
                                id="playlist-sortable-list"
                                data-reorder-url="{{ route('playlist.reorder', $show) }}"
                                class="space-y-2"
                            >
                                @foreach ($playlistItems as $index => $item)
                                    <li
                                        class="playlist-sortable-item flex items-center justify-between gap-4 py-2 border-b border-gray-100"
                                        data-playlist-item-id="{{ $item->id }}"
                                        :class="{ 'playlist-sortable-selected': selected.includes({{ $item->id }}) }"
                                    >
                                        <div class="flex items-center gap-3 min-w-0">
                                            <button
                                                type="button"
                                                class="playlist-drag-handle hidden md:inline-flex items-center justify-center w-8 h-8 text-gray-400 hover:text-gray-600 shrink-0"
                                                aria-label="Drag to reorder"
                                                tabindex="-1"
                                            >
                                                ☰
                                            </button>
                                            <input
                                                type="checkbox"
                                                form="playlist-bulk-remove-form"
                                                name="playlist_item_ids[]"
                                                value="{{ $item->id }}"
                                                x-model.number="selected"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 shrink-0"
                                            >
                                            <span data-position-label class="text-sm text-gray-500 shrink-0">{{ $item->position }}.</span>
                                            <a href="{{ route('songs.show', $item->song) }}" class="font-medium text-indigo-700 hover:text-indigo-900 truncate">
                                                {{ $item->song->song_code }} — {{ $item->song->name }}
                                            </a>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0 md:hidden">
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
                                        </div>
                                    </li>
                                @endforeach
                            </ol>

                            <x-input-error :messages="$errors->get('playlist_item_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('playlist_item_ids.*')" class="mt-2" />
                        </div>
                    @endif

                    <h3 class="font-semibold mb-3">Add Songs to Playlist</h3>

                    @if (! $hasSongsInLibrary)
                        <p class="text-sm text-gray-600">
                            No songs in the library yet.
                            <a href="{{ route('songs.create') }}" class="text-indigo-600 hover:text-indigo-800">Create a song</a>
                            first.
                        </p>
                    @elseif ($availableSongs->isEmpty())
                        <p class="text-sm text-gray-600">All songs have already been added to this playlist.</p>
                    @else
                        <form
                            method="POST"
                            action="{{ route('playlist.store', $show) }}"
                            class="space-y-4"
                            x-data="{
                                selected: @js(collect(old('song_ids', []))->map(fn ($id) => (int) $id)->values()->all()),
                                availableIds: @js($availableSongs->pluck('id')->values()->all()),
                                selectAll() { this.selected = [...this.availableIds]; },
                                clearSelection() { this.selected = []; },
                                get count() { return this.selected.length; },
                            }"
                        >
                            @csrf

                            <div class="flex flex-wrap items-center gap-4 text-sm">
                                <p class="text-gray-700">
                                    Selected: <strong x-text="count"></strong>
                                    <span x-text="count === 1 ? 'song' : 'songs'"></span>
                                </p>
                                <button
                                    type="button"
                                    class="text-indigo-600 hover:text-indigo-800"
                                    @click="selectAll()"
                                >
                                    Select All
                                </button>
                                <button
                                    type="button"
                                    class="text-gray-600 hover:text-gray-800"
                                    @click="clearSelection()"
                                >
                                    Clear Selection
                                </button>
                            </div>

                            <div class="max-h-96 overflow-y-auto border border-gray-200 rounded-md p-3 space-y-2">
                                @foreach ($availableSongs as $song)
                                    <label class="flex items-center gap-2 text-sm py-1">
                                        <input
                                            type="checkbox"
                                            name="song_ids[]"
                                            value="{{ $song->id }}"
                                            x-model.number="selected"
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        <span>{{ $song->song_code }} — {{ $song->name }}</span>
                                    </label>
                                @endforeach
                            </div>

                            <x-input-error :messages="$errors->get('song_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('song_ids.*')" class="mt-2" />

                            <x-primary-button type="submit" ::disabled="count === 0">
                                <span x-text="count === 0 ? 'Add Selected Songs' : ('Add ' + count + ' Song' + (count === 1 ? '' : 's'))"></span>
                            </x-primary-button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
