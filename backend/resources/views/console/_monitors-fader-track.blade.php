@props([
    'handleBottomPct' => 75,
    'trackAttributes' => [],
    'handleAttributes' => [],
])

<div class="vx32-monitors-strip__fader" data-channel-fader-control aria-hidden="true">
    <div class="vx32-monitors-strip__scale" aria-hidden="true">
        @foreach ($faderScaleMarks as $mark)
            <span
                @class([
                    'vx32-monitors-strip__scale-tick',
                    'is-unity' => $mark['unity'],
                ])
                style="bottom: {{ $mark['pct'] }}%;"
            >{{ $mark['label'] }}</span>
        @endforeach
    </div>
    <div
        class="vx32-monitors-strip__track"
        @foreach ($trackAttributes as $attr => $value)
            {{ $attr }}="{{ $value }}"
        @endforeach
    >
        <span
            class="vx32-monitors-strip__track-unity"
            style="bottom: {{ $faderUnityPct }}%;"
            aria-hidden="true"
        ></span>
        @foreach ($faderScaleMarks as $mark)
            <span
                @class([
                    'vx32-monitors-strip__track-tick',
                    'is-unity' => $mark['unity'],
                ])
                style="bottom: {{ $mark['pct'] }}%;"
                aria-hidden="true"
            ></span>
        @endforeach
        <span
            class="vx32-monitors-strip__handle"
            @foreach ($handleAttributes as $attr => $value)
                {{ $attr }}="{{ $value }}"
            @endforeach
            style="bottom: {{ $handleBottomPct }}%;"
        ></span>
    </div>
</div>
