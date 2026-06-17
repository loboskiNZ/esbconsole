@props([
    'show',
    'consoleType',
    'workspaceMode',
    'summary',
    'activeTab' => 'overview',
    'defaultBaselineName' => null,
    'suppressSubbar' => false,
])

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

    @include('console._console-tabs', ['show' => $show, 'activeTab' => $activeTab])

    <div class="vx32-topbar__status">
        @if (($summary['transport'] ?? '') === 'live_osc')
            <span class="vx32-status-pill vx32-status-pill--live">LINK</span>
        @else
            <span class="vx32-status-pill">48K</span>
        @endif
        <span class="vx32-status-pill">{{ strtoupper($consoleType->value) }}</span>
        @if ($workspaceMode === 'preview' && $defaultBaselineName !== null)
            <form method="POST" action="{{ route('shows.console.save', $show) }}" class="vx32-save-form">
                @csrf
                <input type="hidden" name="baseline_name" value="{{ old('baseline_name', $defaultBaselineName) }}" />
                <button type="submit" class="vx32-save-form__btn">Save</button>
            </form>
        @endif
        <a href="{{ route('shows.console.learn', $show) }}" class="vx32-topbar__learn">Learn</a>
    </div>
</header>

@if (! $suppressSubbar)
<div class="vx32-subbar">
    <div class="vx32-subbar__meta vx32-subbar__meta--wide">
        <span>Scene {{ $summary['scene_number'] ?? '—' }}</span>
        @if ($workspaceMode === 'preview')
            <span class="vx32-subbar__badge">Unsaved preview</span>
        @else
            <span class="vx32-subbar__badge vx32-subbar__badge--saved">Active</span>
        @endif
        @if ($activeTab === 'routing')
            <span class="vx32-subbar__badge vx32-subbar__badge--routing">Audio routing workspace</span>
        @elseif ($activeTab === 'monitor')
            <span class="vx32-subbar__badge vx32-subbar__badge--routing">Monitor bus workspace</span>
        @endif
    </div>
</div>
@endif
