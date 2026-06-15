<x-console-layout>
    <div
        class="vx32-console"
        x-data="virtualConsole({
            strips: @js($virtualStrips),
            controlUpdateUrl: @js($controlUpdateUrl),
        })"
    >
        <header class="vx32-topbar">
            <div class="vx32-topbar__brand">
                <span class="vx32-topbar__app">ESB Console</span>
                <nav class="vx32-topbar__crumb" aria-label="Breadcrumb">
                    <a href="{{ route('shows.index') }}">Shows</a>
                    <span aria-hidden="true">›</span>
                    <a href="{{ route('shows.show', $show) }}">{{ $show->name }}</a>
                    <span aria-hidden="true">›</span>
                    <span>Console</span>
                </nav>
            </div>

            @include('console._console-tabs', ['show' => $show, 'activeTab' => 'overview'])

            <div class="vx32-topbar__status">
                @if (($summary['transport'] ?? '') === 'live_osc')
                    <span class="vx32-status-pill vx32-status-pill--live">LINK</span>
                @else
                    <span class="vx32-status-pill">48K</span>
                @endif
                <span class="vx32-status-pill">{{ strtoupper($consoleType->value) }}</span>
                @if ($workspaceMode === 'preview')
                    <form method="POST" action="{{ route('shows.console.save', $show) }}" class="vx32-save-form">
                        @csrf
                        <input type="hidden" name="baseline_name" value="{{ old('baseline_name', $defaultBaselineName) }}" />
                        <button type="submit" class="vx32-save-form__btn">Save</button>
                    </form>
                @endif
                <a href="{{ route('shows.console.learn', $show) }}" class="vx32-topbar__learn">Learn</a>
            </div>
        </header>

        <div class="vx32-subbar">
            <nav class="vx32-layers" aria-label="Console layers">
                <button type="button" class="vx32-layers__btn is-active">CH 1-32</button>
                <button type="button" class="vx32-layers__btn" disabled>AUX / USB</button>
                <button type="button" class="vx32-layers__btn" disabled>FX RETURNS</button>
                <button type="button" class="vx32-layers__btn" disabled>BUS 1-16</button>
                <button type="button" class="vx32-layers__btn" disabled>DCA 1-8</button>
                <button type="button" class="vx32-layers__btn" disabled>MATRIX</button>
            </nav>

            <div class="vx32-subbar__meta">
                <span>Scene {{ $summary['scene_number'] ?? '—' }}</span>
                @if ($workspaceMode === 'preview')
                    <span class="vx32-subbar__badge">Unsaved preview</span>
                @else
                    <span class="vx32-subbar__badge vx32-subbar__badge--saved">Active</span>
                @endif
            </div>
        </div>

        @if (! empty($consoleMetadataIncomplete))
            <div class="vx32-notice">
                Desk names and colours are missing — <a href="{{ route('shows.console.learn', $show) }}">Learn again</a>.
            </div>
        @endif

        <div class="vx32-rack" x-show="activeTab === 'overview'">
            <div class="vx32-strips">
                @foreach ($virtualStrips as $strip)
                    @include('console._virtual-channel-strip', [
                        'strip' => $strip,
                        'color' => \App\Services\X32\X32ChannelColorMap::resolve($strip['color'] ?? 0),
                    ])
                @endforeach
            </div>
        </div>
    </div>
</x-console-layout>
