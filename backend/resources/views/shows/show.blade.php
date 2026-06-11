<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $show->name }}</h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('shows.edit', $show) }}" class="text-indigo-600 hover:text-indigo-800">Edit Show</a>
                <a href="{{ route('playlist.show', $show) }}" class="text-indigo-600 hover:text-indigo-800">Manage Playlist</a>
                <a href="{{ route('shows.index') }}" class="text-gray-600 hover:text-gray-800">← Shows</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-2">Band: <strong>{{ $band->name }}</strong></p>
                    <p class="text-sm text-gray-600 mb-4">State: <strong>{{ $show->lifecycle_state }}</strong></p>
                    @if ($show->description)
                        <p class="text-sm text-gray-700 mb-4">{{ $show->description }}</p>
                    @endif

                    <h3 class="font-semibold text-lg mb-3">Playlist ({{ $show->playlistItems->count() }} songs)</h3>

                    @if ($show->playlistItems->isEmpty())
                        <p class="text-sm text-gray-600 mb-4">No songs on this playlist yet.</p>
                        <a href="{{ route('playlist.show', $show) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Add songs to playlist →</a>
                    @else
                        <ol class="list-decimal list-inside space-y-3 mb-4">
                            @foreach ($show->playlistItems as $item)
                                <li class="text-sm">
                                    <a href="{{ route('songs.show', $item->song) }}" class="font-medium text-indigo-700 hover:text-indigo-900">
                                        {{ $item->song->song_code }} — {{ $item->song->name }}
                                    </a>
                                    <span class="text-gray-500">
                                        · {{ $item->song->cues->count() }} cue(s)
                                        · {{ $item->song->songInstrumentParts->count() }} part(s)
                                    </span>
                                </li>
                            @endforeach
                        </ol>
                        <a href="{{ route('playlist.show', $show) }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Manage playlist →</a>
                    @endif
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <a href="{{ route('songs.create') }}" class="bg-white p-6 rounded-lg shadow-sm hover:shadow border border-gray-100">
                    <h3 class="font-semibold text-gray-900">Create Song</h3>
                    <p class="text-sm text-gray-600 mt-2">Add a new song to the library.</p>
                </a>
                <a href="{{ route('instrument-parts.index') }}" class="bg-white p-6 rounded-lg shadow-sm hover:shadow border border-gray-100">
                    <h3 class="font-semibold text-gray-900">Instrument Parts</h3>
                    <p class="text-sm text-gray-600 mt-2">Manage the band instrument part catalog.</p>
                </a>
                <a href="{{ route('people.index') }}" class="bg-white p-6 rounded-lg shadow-sm hover:shadow border border-gray-100">
                    <h3 class="font-semibold text-gray-900">Band People</h3>
                    <p class="text-sm text-gray-600 mt-2">Manage crew, musicians, and band roles.</p>
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
