@props(['show', 'activeTab' => 'overview'])

<nav class="vx32-tabs" aria-label="Console functions">
    @foreach (['overview' => 'Overview', 'routing' => 'Routing', 'configuration' => 'Configuration', 'effects' => 'Effects', 'scenes' => 'Scenes', 'snippets' => 'Snippets', 'monitor' => 'Monitor', 'setup' => 'Setup'] as $tabKey => $tabLabel)
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
        @elseif ($tabKey === 'configuration')
            <a
                href="{{ route('shows.console.configuration', $show) }}"
                class="vx32-tabs__btn {{ $activeTab === 'configuration' ? 'is-active' : '' }}"
            >{{ strtoupper($tabLabel) }}</a>
        @elseif ($tabKey === 'effects')
            <a
                href="{{ route('shows.console.effects', $show) }}"
                class="vx32-tabs__btn {{ $activeTab === 'effects' ? 'is-active' : '' }}"
            >{{ strtoupper($tabLabel) }}</a>
        @elseif ($tabKey === 'monitor')
            <a
                href="{{ route('shows.console.bus.layout', [$show, 1]) }}"
                class="vx32-tabs__btn {{ $activeTab === 'monitor' ? 'is-active' : '' }}"
            >{{ strtoupper($tabLabel) }}</a>
        @else
            <button type="button" class="vx32-tabs__btn" disabled>{{ strtoupper($tabLabel) }}</button>
        @endif
    @endforeach
</nav>
