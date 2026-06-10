<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Playlist — {{ $show->name }}
            </h2>
            <a href="{{ route('shows.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Shows</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-4">
                        Band: <strong>{{ $band->name }}</strong> · Show: <strong>{{ $show->name }}</strong>
                    </p>

                    @if ($playlistItems->isEmpty())
                        <p>This show has no playlist items.</p>
                    @else
                        <ol class="list-decimal list-inside space-y-2">
                            @foreach ($playlistItems as $item)
                                <li>
                                    <span class="font-medium">{{ $item->song->name }}</span>
                                    @if ($item->ableton_pgm)
                                        <span class="text-sm text-gray-500">(PGM {{ $item->ableton_pgm }})</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
