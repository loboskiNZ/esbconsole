<div x-data="{ tab: 'channels' }" class="space-y-4">
    <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
        @foreach (['channels' => 'Channels', 'buses' => 'Buses / Monitors', 'dcas' => 'DCAs', 'matrices' => 'Matrices', 'fx' => 'FX', 'routing' => 'Routing'] as $key => $label)
            <button
                type="button"
                @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                class="px-3 py-1.5 rounded-md text-xs font-semibold uppercase tracking-wide transition"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <p class="text-xs text-slate-500">Read-only show console view — baseline data only, no live desk control.</p>

    <div x-show="tab === 'channels'" x-cloak>
        @if (! empty($channels))
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-2 min-w-max">
                    @foreach ($channels as $channel)
                        @include('console._channel-strip', ['strip' => $channel, 'labelPrefix' => 'CH'])
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500">No channel data.</p>
        @endif
    </div>

    <div x-show="tab === 'buses'" x-cloak>
        @if (! empty($buses))
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-2 min-w-max">
                    @foreach ($buses as $bus)
                        @include('console._channel-strip', ['strip' => $bus, 'labelPrefix' => 'BUS'])
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500">No bus data.</p>
        @endif
    </div>

    <div x-show="tab === 'dcas'" x-cloak>
        @if (! empty($dcas))
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-2 min-w-max">
                    @foreach ($dcas as $dca)
                        @include('console._channel-strip', ['strip' => $dca, 'labelPrefix' => 'DCA'])
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500">No DCA data.</p>
        @endif
    </div>

    <div x-show="tab === 'matrices'" x-cloak>
        @if (! empty($matrices))
            <div class="overflow-x-auto pb-2">
                <div class="flex gap-2 min-w-max">
                    @foreach ($matrices as $matrix)
                        @include('console._channel-strip', ['strip' => $matrix, 'labelPrefix' => 'MTRX'])
                    @endforeach
                </div>
            </div>
        @else
            <p class="text-sm text-gray-500">No matrix data.</p>
        @endif
    </div>

    <div x-show="tab === 'fx'" x-cloak>
        @if (! empty($fx))
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @foreach ($fx as $slot)
                    <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs font-bold text-slate-500 uppercase">FX {{ $slot['slot'] ?? '—' }}</p>
                        <p class="font-semibold text-slate-900 mt-1">{{ $slot['name'] ?? '—' }}</p>
                        <p class="text-sm text-slate-600 mt-1">{{ $slot['type'] ?? '—' }}</p>
                        <span class="inline-block mt-2 text-xs px-2 py-0.5 rounded {{ ! empty($slot['enabled']) ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                            {{ ! empty($slot['enabled']) ? 'Enabled' : 'Off' }}
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">No FX data.</p>
        @endif
    </div>

    <div x-show="tab === 'routing'" x-cloak>
        @if (! empty($routing))
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm space-y-2">
                @foreach ($routing as $key => $value)
                    <div class="flex gap-2">
                        <span class="font-medium text-slate-700 capitalize min-w-[8rem]">{{ str_replace('_', ' ', $key) }}</span>
                        <span class="text-slate-600">
                            @if (is_array($value))
                                {{ json_encode($value) }}
                            @else
                                {{ $value }}
                            @endif
                        </span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-gray-500">No routing summary.</p>
        @endif
    </div>
</div>
