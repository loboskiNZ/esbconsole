<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Shows — {{ $band->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-4">Active band: <strong>{{ $band->name }}</strong></p>

                    @if ($shows->isEmpty())
                        <p>No shows found. Run seeders or create shows locally.</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($shows as $show)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $show->name }}</p>
                                        <p class="text-sm text-gray-500">{{ $show->lifecycle_state }}</p>
                                        @if ($activeShowId === $show->id)
                                            <span class="inline-block mt-1 text-xs font-semibold text-green-700 bg-green-100 px-2 py-0.5 rounded">Active</span>
                                        @endif
                                    </div>
                                    <div class="flex gap-2">
                                        <form method="POST" action="{{ route('shows.activate', $show) }}">
                                            @csrf
                                            <x-primary-button type="submit">
                                                {{ $activeShowId === $show->id ? 'Open Playlist' : 'Set Active' }}
                                            </x-primary-button>
                                        </form>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
