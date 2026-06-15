<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Console Baseline Record (Admin)</h2>
            <div class="flex gap-4 text-sm">
                <a href="{{ route('shows.show', $baseline->show) }}" class="text-indigo-600 hover:text-indigo-800 font-medium">← Back to {{ $baseline->show->name }}</a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded text-sm">
                Admin/debug view. Directors use <a href="{{ route('shows.console', $baseline->show) }}" class="underline font-medium">{{ $baseline->show->name }} Console</a>.
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Baseline</p>
                            <p class="font-medium">{{ $baseline->baseline_name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Show</p>
                            <p class="font-medium">{{ $baseline->show->name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Console</p>
                            <p class="font-medium">{{ $baseline->sourceSnapshot->integrationDevice->name ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Type / Saved</p>
                            <p class="font-medium">{{ strtoupper($baseline->console_type->value) }} · {{ $baseline->saved_at?->format('Y-m-d H:i') }}</p>
                        </div>
                    </div>
                    <p class="text-sm text-gray-600">
                        Scene {{ $summary['scene_number'] ?? $baseline->sourceSnapshot->requested_scene_number ?? '—' }}
                        @if ($baseline->active) · <span class="text-green-700">Active baseline</span> @endif
                    </p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="font-semibold mb-4">Virtual Console Baseline</h3>
                    @include('console._virtual-console')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
