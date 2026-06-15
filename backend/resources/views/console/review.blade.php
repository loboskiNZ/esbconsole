<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Review Learned Console</h2>
            <a href="{{ route('shows.show', $snapshot->show) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← {{ $snapshot->show->name }}</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-4">
                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                        <div>
                            <p class="text-gray-500">Show</p>
                            <p class="font-medium">{{ $snapshot->show->name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Console</p>
                            <p class="font-medium">{{ $snapshot->integrationDevice->name }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Requested scene</p>
                            <p class="font-medium">{{ $snapshot->requested_scene_number }}</p>
                        </div>
                        <div>
                            <p class="text-gray-500">Status</p>
                            <p class="font-medium">{{ $snapshot->learning_status->label() }}</p>
                        </div>
                    </div>

                    @if (! empty($snapshot->warnings_json))
                        <div class="bg-amber-50 border border-amber-200 rounded p-4 text-sm text-amber-900">
                            <p class="font-medium mb-2">Warnings</p>
                            <ul class="list-disc ms-5 space-y-1">
                                @foreach ($snapshot->warnings_json as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! empty($snapshot->errors_json))
                        <div class="bg-red-50 border border-red-200 rounded p-4 text-sm text-red-900">
                            <p class="font-medium mb-2">Errors</p>
                            <ul class="list-disc ms-5 space-y-1">
                                @foreach ($snapshot->errors_json as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </div>

            @if ($snapshot->learning_status === \App\Enums\ConsoleLearningStatus::Review)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <h3 class="font-semibold mb-4">Virtual Console</h3>
                        @include('console._virtual-console')
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900">
                        <form method="POST" action="{{ route('console.snapshots.save-baseline', $snapshot) }}" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="baseline_name" value="Baseline name (optional)" />
                                <x-text-input
                                    id="baseline_name"
                                    name="baseline_name"
                                    type="text"
                                    class="mt-1 block w-full max-w-xl"
                                    :value="old('baseline_name', 'Scene '.($summary['scene_number'] ?? $snapshot->requested_scene_number).' — '.$snapshot->integrationDevice->name)"
                                />
                            </div>
                            <x-primary-button type="submit">Save Baseline</x-primary-button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
