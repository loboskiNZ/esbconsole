<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                @if ($show)
                    Learn Console — {{ $show->name }}
                @else
                    Learn Console Scene — {{ $band->name }}
                @endif
            </h2>
            @if ($show)
                @if ($hasActiveBaseline)
                    <a href="{{ route('shows.console', $show) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Console</a>
                @else
                    <a href="{{ route('shows.show', $show) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Back to Show</a>
                @endif
            @else
                <a href="{{ route('console.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Console</a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 space-y-6">
                    @if (! empty(session('learning_errors')))
                        <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded text-sm space-y-1">
                            <p class="font-medium">Learning failed:</p>
                            <ul class="list-disc list-inside">
                                @foreach ((array) session('learning_errors') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($show)
                        <p class="text-sm text-gray-600">
                            @if ($hasActiveBaseline)
                                Learn a scene from the console device. You will return to the console workspace to review and save.
                            @else
                                Which scene should be used as the baseline for this show?
                            @endif
                        </p>
                    @else
                        <p class="text-sm text-gray-600">
                            Console baselines are normally learned from inside a Show.
                            Learning reads the console through the OSC bridge and opens the show console workspace to review and save.
                        </p>
                    @endif

                    <form
                        method="POST"
                        action="{{ $show ? route('shows.console.learn.store', $show) : route('console.learn.store') }}"
                        class="space-y-6"
                        x-data="{ learning: false }"
                        @submit="learning = true"
                    >
                        @csrf

                        @if (! $show)
                            <div>
                                <x-input-label for="show_id" value="Show" />
                                <select id="show_id" name="show_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">Select show…</option>
                                    @foreach ($shows as $showOption)
                                        <option value="{{ $showOption->id }}" @selected(old('show_id') == $showOption->id)>{{ $showOption->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('show_id')" class="mt-2" />
                            </div>
                        @endif

                        <div>
                            <x-input-label for="integration_device_id" value="X32/M32 console device" />
                            <select id="integration_device_id" name="integration_device_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                <option value="">Select console…</option>
                                @foreach ($consoleDevices as $device)
                                    @php
                                        $mode = app(\App\Services\X32\X32RuntimeModeResolver::class)->resolve($device->configuration ?? []);
                                        $profile = $device->integrationConnectionProfiles->first();
                                    @endphp
                                    <option value="{{ $device->id }}" @selected(old('integration_device_id') == $device->id)>
                                        {{ $device->name }} ({{ $device->device_key }}) — {{ $profile?->host ?? 'no host' }}:{{ $profile?->port ?? '10023' }} — mode: {{ $mode }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-amber-700 mt-2">
                                Learning reads the real desk only when device <code class="text-xs">runtime_mode</code> is <strong>live</strong>.
                                Default is <strong>dry_run</strong>, which will not recall scenes or query the console.
                            </p>
                            <x-input-error :messages="$errors->get('integration_device_id')" class="mt-2" />
                            @if ($consoleDevices->isEmpty())
                                <p class="text-xs text-amber-700 mt-2">No enabled X32/M32 devices configured for this band.</p>
                            @endif
                        </div>

                        <div>
                            <x-input-label for="requested_scene_number" value="{{ $show ? 'Scene number' : 'Starting scene number' }}" />
                            <x-text-input id="requested_scene_number" name="requested_scene_number" type="text" class="mt-1 block w-full max-w-xs" :value="old('requested_scene_number', '01')" required />
                            <p class="text-xs text-gray-500 mt-1">Example: 01</p>
                            <x-input-error :messages="$errors->get('requested_scene_number')" class="mt-2" />
                        </div>

                        <x-primary-button type="submit" x-bind:disabled="learning">
                            <span x-show="!learning">Learn Scene</span>
                            <span x-show="learning">Learning…</span>
                        </x-primary-button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
