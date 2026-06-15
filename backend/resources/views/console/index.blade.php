<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Console — {{ $band->name }}</h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-blue-50 border border-blue-200 text-blue-900 px-4 py-3 rounded text-sm">
                Console baselines are normally learned from inside a Show. Open a show and use the Console section to learn or review its baseline.
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Active Show Console Baselines</h3>
                    @if ($activeBaselines->isEmpty())
                        <p class="text-sm text-gray-600">No saved console baselines yet.</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($activeBaselines as $baseline)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $baseline->baseline_name }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ $baseline->show->name }} · {{ strtoupper($baseline->console_type->value) }} · Saved {{ $baseline->saved_at?->format('Y-m-d H:i') }}
                                        </p>
                                    </div>
                                    <div class="flex gap-3 text-sm">
                                        <a href="{{ route('shows.console', $baseline->show) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">Console</a>
                                        <a href="{{ route('shows.show', $baseline->show) }}" class="text-indigo-600 hover:text-indigo-800">Show</a>
                                        <a href="{{ route('console.baselines.show', $baseline) }}" class="text-gray-500 hover:text-gray-700">Admin</a>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Recent Learning Snapshots</h3>
                    @if ($recentSnapshots->isEmpty())
                        <p class="text-sm text-gray-600">No learning snapshots yet.</p>
                    @else
                        <ul class="divide-y divide-gray-200">
                            @foreach ($recentSnapshots as $snapshot)
                                <li class="py-3 flex items-center justify-between gap-4">
                                    <div>
                                        <p class="font-medium">{{ $snapshot->show->name }} — Scene {{ $snapshot->requested_scene_number }}</p>
                                        <p class="text-sm text-gray-500">
                                            {{ $snapshot->integrationDevice->name }} · {{ $snapshot->learning_status->label() }}
                                            @if ($snapshot->learned_at) · {{ $snapshot->learned_at->format('Y-m-d H:i') }} @endif
                                        </p>
                                    </div>
                                    <a href="{{ route('shows.console', $snapshot->show) }}" class="text-sm text-indigo-600 hover:text-indigo-800">Console</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
