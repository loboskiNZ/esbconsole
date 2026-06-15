@php
    $channelNumber = (int) $strip['channelNumber'];
@endphp

<div
    class="vx32-channel-strip"
    data-channel-number="{{ $channelNumber }}"
    style="--strip-color: {{ $color['css'] }};"
    x-data="{ channelNumber: {{ $channelNumber }} }"
    :class="{
        'is-muted-strip': stripByChannel(channelNumber)?.muted,
        'is-illuminated': ! stripByChannel(channelNumber)?.muted && (stripByChannel(channelNumber)?.faderLevel ?? 0) > 0,
    }"
>
    <div class="vx32-channel-strip__upper">
        <div class="vx32-channel-strip__head">
            <div class="vx32-channel-strip__number">{{ $channelNumber }}</div>
            <div
                class="vx32-channel-strip__nameplate"
                style="color: {{ $color['text'] }};"
            >
                <span class="vx32-channel-strip__name" x-text="stripByChannel(channelNumber)?.name">{{ $strip['name'] }}</span>
            </div>
        </div>

        <div class="vx32-channel-strip__meter" aria-hidden="true">
            <div class="vx32-channel-strip__meter-track">
                <div
                    class="vx32-channel-strip__meter-fill"
                    :style="{ height: meterHeight(stripByChannel(channelNumber)) }"
                ></div>
            </div>
        </div>

        <div class="vx32-channel-strip__controls">
            <button
                type="button"
                class="vx32-channel-strip__mute"
                :class="{ 'is-muted': stripByChannel(channelNumber)?.muted }"
                @click="toggleBoolControl(channelNumber, 'mute')"
            >MUTE</button>

            <div class="vx32-channel-strip__gain-block">
                <div
                    class="vx32-knob"
                    @pointerdown="pointerDownKnob($event, channelNumber, 'gain')"
                    @pointermove="pointerMoveKnob($event, channelNumber, 'gain')"
                    @pointerup="pointerUpKnob($event, channelNumber, 'gain')"
                    @pointercancel="pointerUpKnob($event, channelNumber, 'gain')"
                >
                    <div class="vx32-knob__dial" :style="knobRotation(stripByChannel(channelNumber), 'gain')"></div>
                </div>
                <div class="vx32-channel-strip__readout" x-text="gainLabel(stripByChannel(channelNumber))">0.0</div>
                <div class="vx32-channel-strip__label">GAIN</div>
            </div>

            <div class="vx32-channel-strip__proc">
                <button type="button" class="vx32-proc-btn vx32-proc-btn--48v" :class="{ 'is-on': stripByChannel(channelNumber)?.phantom48v }" @click="toggleBoolControl(channelNumber, 'phantom48v')">48V</button>
                <button type="button" class="vx32-proc-btn vx32-proc-btn--gate" :class="{ 'is-on': stripByChannel(channelNumber)?.gateOn }" @click="toggleBoolControl(channelNumber, 'gate_on')">GATE</button>
                <button type="button" class="vx32-proc-btn vx32-proc-btn--comp" :class="{ 'is-on': stripByChannel(channelNumber)?.compressorOn }" @click="toggleBoolControl(channelNumber, 'compressor_on')">COMP</button>
                <button type="button" class="vx32-proc-btn vx32-proc-btn--eq" :class="{ 'is-on': stripByChannel(channelNumber)?.eqOn }" @click="toggleBoolControl(channelNumber, 'eq_on')">EQ</button>
                <button type="button" class="vx32-proc-btn vx32-proc-btn--sends" :class="{ 'is-on': stripByChannel(channelNumber)?.sendsOpen }" @click="toggleBoolControl(channelNumber, 'sends')">SENDS</button>
            </div>
        </div>

        <div class="vx32-channel-strip__routing">
            <div class="vx32-channel-strip__pan-block">
                <div
                    class="vx32-knob vx32-knob--pan"
                    @pointerdown="pointerDownKnob($event, channelNumber, 'pan')"
                    @pointermove="pointerMoveKnob($event, channelNumber, 'pan')"
                    @pointerup="pointerUpKnob($event, channelNumber, 'pan')"
                    @pointercancel="pointerUpKnob($event, channelNumber, 'pan')"
                >
                    <div class="vx32-knob__dial" :style="knobRotation(stripByChannel(channelNumber), 'pan')"></div>
                </div>
                <div class="vx32-channel-strip__readout" x-text="panLabel(stripByChannel(channelNumber))">C</div>
                <div class="vx32-channel-strip__label">PAN</div>
            </div>

            <div class="vx32-channel-strip__assign">
                <button type="button" class="vx32-assign-btn" :class="{ 'is-on': stripByChannel(channelNumber)?.linked }" @click="toggleBoolControl(channelNumber, 'stereo_link')">LINK</button>
                <button type="button" class="vx32-assign-btn vx32-assign-btn--lr" :class="{ 'is-on': stripByChannel(channelNumber)?.mainLr }" @click="toggleBoolControl(channelNumber, 'main_lr')">L/R</button>
            </div>
        </div>
    </div>

    <div
        class="vx32-channel-strip__fader-zone"
        @pointerdown="pointerDownFader($event, channelNumber)"
        @pointermove="pointerMoveFader($event, channelNumber)"
        @pointerup="pointerUpFader($event, channelNumber)"
        @pointercancel="pointerUpFader($event, channelNumber)"
    >
        <div class="vx32-channel-strip__fader-row">
            <div class="vx32-fader-scale" aria-hidden="true">
                <template x-for="tick in faderScaleTicks" :key="tick.label">
                    <div class="vx32-fader-scale__tick" :class="tick.unity ? 'is-unity' : ''" :style="tickStyle(tick)">
                        <span x-text="tick.label"></span>
                    </div>
                </template>
            </div>

            <div class="vx32-fader-track">
                <div class="vx32-fader-track__unity"></div>
                <template x-for="tick in faderScaleTicks" :key="`track-${tick.label}`">
                    <div class="vx32-fader-track__tick" :class="tick.unity ? 'is-unity' : ''" :style="tickStyle(tick)"></div>
                </template>
                <div
                    class="vx32-fader-track__fill"
                    :style="faderFillStyle(stripByChannel(channelNumber))"
                ></div>
                <div
                    class="vx32-fader-track__cap"
                    :style="faderCapStyle(stripByChannel(channelNumber))"
                ></div>
            </div>
        </div>

        <div class="vx32-channel-strip__fader-value" x-text="faderDbLabel(stripByChannel(channelNumber))">-∞</div>
    </div>
</div>
