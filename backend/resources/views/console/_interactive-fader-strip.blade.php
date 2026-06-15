@props(['strip', 'labelPrefix' => 'CH', 'parameterUpdateUrl'])

@php
    use App\Services\X32\X32ChannelColorMap;
    use App\Services\X32\X32FaderScale;
    use App\Services\X32\X32StripLabelHelper;

    $index = (int) ($strip['index'] ?? 0);
    $displayIndex = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $channelName = trim((string) ($strip['name'] ?? ''));
    $displayName = X32StripLabelHelper::displayName($channelName, $index, $labelPrefix);
    $color = X32ChannelColorMap::resolve(isset($strip['color']) ? (int) $strip['color'] : null);
    $fader = min(1.0, max(0.0, (float) ($strip['fader'] ?? 0)));
    $faderPct = X32FaderScale::linearMarkPercent($fader);
    $faderDbLabel = X32FaderScale::formatDb(X32FaderScale::linearToDb($fader));
    $unityPct = X32FaderScale::unityMarkPercent();
    $scaleMarks = X32FaderScale::scaleMarks();
@endphp

<div
    class="x32-strip"
    x-data="x32Strip({
        fader: {{ $fader }},
        mute: {{ ! empty($strip['mute']) ? 'true' : 'false' }},
        oscFader: @js($strip['osc_fader'] ?? ''),
        oscOn: @js($strip['osc_on'] ?? ''),
        updateUrl: @js($parameterUpdateUrl),
    })"
    :class="{ 'is-saving': saving, 'is-dragging': dragging }"
>
    <div class="x32-strip__header">
        <div
            class="x32-strip__color-bar"
            style="background-color: {{ $color['css'] }};"
            title="{{ $color['label'] }}"
            aria-hidden="true"
        ></div>
        <div
            class="x32-strip__scribble"
            style="--scribble-bg: {{ $color['css'] }}; color: {{ $color['text'] }};"
        >
            <div class="x32-strip__index">{{ $labelPrefix }} {{ $displayIndex }}</div>
            <div class="x32-strip__name" title="{{ $displayName ?? $channelName }}">
                {{ $displayName ?? '···' }}
            </div>
        </div>
    </div>

    <div
        class="x32-strip__fader-row"
        x-ref="track"
        @pointerdown.prevent="pointerDown($event)"
        @pointermove="pointerMove($event)"
        @pointerup="pointerUp($event)"
        @pointercancel="pointerUp($event)"
    >
        <div class="x32-strip__meter" aria-hidden="true">
            <div
                class="x32-strip__meter-fill"
                :style="meterStyle()"
                style="height: {{ max(4, $faderPct) }}%;"
            ></div>
        </div>

        <div class="x32-strip__fader-well">
            <div
                class="x32-strip__unity-line"
                style="bottom: {{ $unityPct }}%;"
                aria-hidden="true"
            >
                <span class="x32-strip__unity-label">0</span>
            </div>

            <div class="x32-strip__scale-rail" aria-hidden="true">
                @foreach ($scaleMarks as $mark)
                    @php
                        $markPct = X32FaderScale::linearMarkPercent($mark['linear']);
                        $isUnity = ! empty($mark['unity']);
                    @endphp
                    <div
                        class="x32-strip__scale-tick {{ $mark['major'] ? 'is-major' : 'is-minor' }} {{ $isUnity ? 'is-unity' : '' }}"
                        style="bottom: {{ $markPct }}%;"
                    >
                        @if ($mark['label'])
                            <span class="x32-strip__scale-label">{{ $mark['label'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>

            <div
                class="x32-strip__track"
                role="slider"
                :aria-valuenow="faderDbLabel()"
                aria-valuemin="-60"
                aria-valuemax="10"
                aria-label="Fader {{ $labelPrefix }} {{ $displayIndex }}"
            >
                <div class="x32-strip__track-slot">
                    @foreach ($scaleMarks as $mark)
                        <div
                            class="x32-strip__track-tick {{ ! empty($mark['unity']) ? 'is-unity' : ($mark['major'] ? 'is-major' : 'is-minor') }}"
                            style="bottom: {{ X32FaderScale::linearMarkPercent($mark['linear']) }}%;"
                            aria-hidden="true"
                        ></div>
                    @endforeach
                    <div
                        class="x32-strip__track-fill"
                        :style="fillStyle()"
                        style="height: {{ $faderPct }}%;"
                    ></div>
                    <div
                        class="x32-strip__cap"
                        :style="capStyle()"
                        style="bottom: calc({{ $faderPct }}% - 8px);"
                    ></div>
                </div>
            </div>
        </div>
    </div>

    <div class="x32-strip__value" x-text="faderDbLabel()">{{ $faderDbLabel }}</div>

    <button
        type="button"
        class="x32-strip__mute"
        :class="{ 'is-muted': mute }"
        :disabled="!oscOn || saving"
        @click="toggleMute()"
        x-text="mute ? 'Mute' : 'On'"
    >{{ ! empty($strip['mute']) ? 'Mute' : 'On' }}</button>
</div>
