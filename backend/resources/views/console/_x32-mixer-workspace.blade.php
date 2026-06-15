<div
    x-data="{ bank: 'channels' }"
    class="flex flex-col flex-1 min-h-0 overflow-hidden"
>
    <nav class="x32-console-banks" aria-label="Console banks">
        @foreach ([
            'channels' => 'Channels 1–32',
            'buses' => 'Buses / Monitors',
            'dcas' => 'DCAs',
            'matrices' => 'Matrices',
            'fx' => 'FX Returns',
            'routing' => 'Routing',
        ] as $key => $label)
            <button
                type="button"
                @click="bank = '{{ $key }}'"
                :class="bank === '{{ $key }}' ? 'is-active' : ''"
                class="x32-console-bank-btn"
            >
                {{ $label }}
            </button>
        @endforeach
    </nav>

    <div class="x32-console-rack">
        <div x-show="bank === 'channels'" class="x32-console-panel">
            <div class="x32-console-strips">
                @forelse ($channels as $channel)
                    @include('console._interactive-fader-strip', [
                        'strip' => $channel,
                        'labelPrefix' => 'CH',
                        'parameterUpdateUrl' => $parameterUpdateUrl,
                    ])
                @empty
                    <p class="text-sm text-zinc-500 text-center p-4 w-full">No channel data.</p>
                @endforelse
            </div>
        </div>

        <div x-show="bank === 'buses'" class="x32-console-panel">
            <div class="x32-console-strips">
                @forelse ($buses as $bus)
                    @include('console._interactive-fader-strip', [
                        'strip' => $bus,
                        'labelPrefix' => 'BUS',
                        'parameterUpdateUrl' => $parameterUpdateUrl,
                    ])
                @empty
                    <p class="text-sm text-zinc-500 text-center p-4 w-full">No bus data.</p>
                @endforelse
            </div>
        </div>

        <div x-show="bank === 'dcas'" class="x32-console-panel">
            <div class="x32-console-strips">
                @forelse ($dcas as $dca)
                    @include('console._interactive-fader-strip', [
                        'strip' => $dca,
                        'labelPrefix' => 'DCA',
                        'parameterUpdateUrl' => $parameterUpdateUrl,
                    ])
                @empty
                    <p class="text-sm text-zinc-500 text-center p-4 w-full">No DCA data.</p>
                @endforelse
            </div>
        </div>

        <div x-show="bank === 'matrices'" class="x32-console-panel">
            <div class="x32-console-strips">
                @forelse ($matrices as $matrix)
                    @include('console._interactive-fader-strip', [
                        'strip' => $matrix,
                        'labelPrefix' => 'MTRX',
                        'parameterUpdateUrl' => $parameterUpdateUrl,
                    ])
                @empty
                    <p class="text-sm text-zinc-500 text-center p-4 w-full">No matrix data.</p>
                @endforelse
            </div>
        </div>

        <div x-show="bank === 'fx'" class="x32-console-panel x32-console-meta">
            @if (! empty($fx))
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    @foreach ($fx as $slot)
                        <div class="rounded border border-zinc-700 bg-zinc-900 p-3">
                            <p class="text-[10px] font-bold text-zinc-500 uppercase">FX {{ $slot['slot'] ?? '—' }}</p>
                            <p class="font-semibold mt-1 text-zinc-100">{{ $slot['name'] ?? '—' }}</p>
                            <p class="text-xs text-zinc-400 mt-1">{{ $slot['type'] ?? '—' }}</p>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-zinc-500 text-center">No FX data.</p>
            @endif
        </div>

        <div x-show="bank === 'routing'" class="x32-console-panel x32-console-meta">
            @if (! empty($routing))
                <dl class="x32-console-meta-grid">
                    @foreach ($routing as $key => $value)
                        <div>
                            <dt>{{ str_replace('_', ' ', $key) }}</dt>
                            <dd>{{ is_array($value) ? json_encode($value) : $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-zinc-500 text-center">No routing summary.</p>
            @endif
        </div>
    </div>
</div>
