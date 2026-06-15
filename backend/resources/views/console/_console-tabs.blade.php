@props(['show', 'activeTab' => 'overview'])

<nav class="vx32-tabs" aria-label="Console functions">
    @foreach (['overview' => 'Overview', 'routing' => 'Routing', 'effects' => 'Effects', 'scenes' => 'Scenes', 'snippets' => 'Snippets', 'monitor' => 'Monitor', 'setup' => 'Setup'] as $tabKey => $tabLabel)
        @if ($tabKey === 'overview')
            <a
                href="{{ route('shows.console', $show) }}"
                class="vx32-tabs__btn {{ $activeTab === 'overview' ? 'is-active' : '' }}"
            >{{ strtoupper($tabLabel) }}</a>
        @elseif ($tabKey === 'routing')
            <a
                href="{{ route('shows.console.routing', $show) }}"
                class="vx32-tabs__btn {{ $activeTab === 'routing' ? 'is-active' : '' }}"
            >{{ strtoupper($tabLabel) }}</a>
        @else
            <button type="button" class="vx32-tabs__btn" disabled>{{ strtoupper($tabLabel) }}</button>
        @endif
    @endforeach
</nav>
