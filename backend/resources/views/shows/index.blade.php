<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Shows — {{ $band->name }}
            </h2>
            <a href="{{ route('shows.create') }}" class="text-sm text-indigo-600 hover:text-indigo-800">+ New Show</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p class="text-sm text-gray-600 mb-4">Active band: <strong>{{ $band->name }}</strong></p>

                    @if ($shows->isEmpty())
                        <p class="mb-4">No shows yet. Create your first show to begin building a playlist.</p>
                        <a href="{{ route('shows.create') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">Create a show →</a>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($shows as $show)
                                <li class="py-4 flex items-center justify-between gap-4">
                                    <div>
                                        <a href="{{ route('shows.show', $show) }}" class="font-medium text-indigo-700 hover:text-indigo-900">
                                            {{ $show->name }}
                                        </a>
                                        <p class="text-sm text-gray-500">{{ $show->lifecycle_state }} · {{ $show->playlist_items_count }} playlist item(s)</p>
                                        @if ($activeShowId === $show->id)
                                            <span class="inline-block mt-1 text-xs font-semibold text-green-700 bg-green-100 px-2 py-0.5 rounded">Active</span>
                                        @endif
                                    </div>
                                    <div class="flex gap-2">
                                        <a href="{{ route('shows.show', $show) }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                            Open
                                        </a>
                                        <form method="POST" action="{{ route('shows.activate', $show) }}">
                                            @csrf
                                            <x-primary-button type="submit">
                                                {{ $activeShowId === $show->id ? 'Active' : 'Set Active' }}
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
