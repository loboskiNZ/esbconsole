<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Songs — {{ $band->name }}</h2>
            <a href="{{ route('songs.create') }}" class="text-sm text-indigo-600 hover:text-indigo-800">+ New Song</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if ($songs->isEmpty())
                        <p class="mb-4">No songs in the library yet.</p>
                        <a href="{{ route('songs.create') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Create a song →</a>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($songs as $song)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <a href="{{ route('songs.show', $song) }}" class="font-medium text-indigo-700 hover:text-indigo-900">
                                            {{ $song->song_code }} — {{ $song->name }}
                                        </a>
                                        <p class="text-sm text-gray-500">{{ $song->status }}@if($song->bpm) · {{ $song->bpm }} BPM @endif</p>
                                    </div>
                                    <a href="{{ route('songs.show', $song) }}" class="text-sm text-gray-600 hover:text-gray-800">Open →</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
