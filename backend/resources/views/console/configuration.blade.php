@php
    $header = $configurationWorkspace['header'];
    $status = $configurationWorkspace['status'];
    $statusLegend = $configurationWorkspace['status_legend'];
    $identity = $configurationWorkspace['identity'];

    $statusRoutingClass = match ($status['state']) {
        'complete' => 'learned',
        'partial' => 'suggested',
        default => 'not-learned',
    };
@endphp

<x-console-layout>
    <div class="vx32-console">
        @include('console._console-header', [
            'show' => $show,
            'consoleType' => $consoleType,
            'workspaceMode' => $workspaceMode,
            'summary' => $summary,
            'activeTab' => 'configuration',
            'suppressSubbar' => true,
        ])

        @include('console._configuration-subbar', [
            'show' => $show,
            'workspaceMode' => $workspaceMode,
            'summary' => $summary,
            'learnedAtDisplay' => $configurationWorkspace['learned_at_display'] ?? null,
        ])

        <div class="vx32-routing-workspace vx32-configuration-workspace">
            <header class="vx32-routing-workspace__header">
                <div class="vx32-routing-workspace__header-left">
                    <span class="vx32-routing-workspace__context">{{ $header['context'] }}</span>
                    <div class="vx32-routing-workspace__title-row">
                        <h1 class="vx32-routing-workspace__title">{{ $header['title'] }}</h1>
                        <span @class([
                            'vx32-routing-workspace__routing-state',
                            'vx32-routing-workspace__routing-state--learned' => $statusRoutingClass === 'learned',
                            'vx32-routing-workspace__routing-state--suggested' => $statusRoutingClass === 'suggested',
                            'vx32-routing-workspace__routing-state--not-learned' => $statusRoutingClass === 'not-learned',
                        ])>{{ strtoupper($status['label']) }}</span>
                    </div>
                    <p class="vx32-configuration-workspace__learn-context">{{ $header['learn_context'] }}</p>
                    <p class="vx32-configuration-workspace__status-hint">{{ $status['hint'] }}</p>
                </div>
                <div class="vx32-routing-workspace__header-actions" aria-label="Related workspaces">
                    <a href="{{ route('shows.console.routing', $show) }}" class="vx32-configuration-workspace__link">
                        View audio routing →
                    </a>
                </div>
            </header>

            <div class="vx32-routing-workspace__content">
                <section class="vx32-routing-detail" aria-label="Identity row">
                    <h2 class="vx32-routing-detail__sr-title">Console identity</h2>

                    <div class="vx32-configuration-identity__grid">
                        @foreach (['console', 'scene', 'clock', 'learn_status'] as $cardKey)
                            @php($card = $identity[$cardKey])
                            <article class="vx32-routing-detail__panel vx32-routing-detail__panel--{{ $cardKey }}">
                                <h3 class="vx32-routing-detail__panel-title">{{ strtoupper($card['title']) }}</h3>

                                <ul class="vx32-routing-detail__status-grid">
                                    @foreach ($card['fields'] as $field)
                                        <li @class([
                                            'vx32-routing-detail__status-tile',
                                            'vx32-routing-detail__status-tile--learned' => ($field['captured'] ?? false) && ! ($field['attention'] ?? false),
                                            'vx32-routing-detail__status-tile--not-learned' => ! ($field['captured'] ?? false) || ($field['attention'] ?? false),
                                        ])>
                                            <span class="vx32-routing-detail__status-tile-label">{{ $field['label'] }}</span>
                                            <span class="vx32-routing-detail__status-tile-value">{{ $field['value'] }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </article>
                        @endforeach
                    </div>
                </section>

                <section class="vx32-routing-detail" aria-labelledby="configuration-status-legend-title">
                    <h2 id="configuration-status-legend-title" class="vx32-routing-flow__title">Configuration Status</h2>

                    <div class="vx32-configuration-status-legend__grid">
                        @foreach ($statusLegend as $item)
                            <article @class([
                                'vx32-routing-detail__panel',
                                'vx32-configuration-status-legend__panel--active' => $item['active'],
                            ])>
                                <header class="vx32-routing-detail__production-head">
                                    <h3 class="vx32-routing-detail__config-name">{{ $item['label'] }}</h3>
                                    @if ($item['active'])
                                        <p class="vx32-routing-detail__type-badge vx32-routing-detail__type-badge--learned">Current status</p>
                                    @endif
                                </header>
                                <p class="vx32-routing-detail__learned-meta">{{ $item['description'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>

                <aside class="vx32-routing-detail__panel vx32-configuration-info-panel" role="note">
                    <h3 class="vx32-routing-detail__panel-title">About this page</h3>
                    <p class="vx32-routing-detail__learned-meta">
                        This page shows the learned configuration of your X32 console. It does not show routing or connectivity.
                        Use the <a href="{{ route('shows.console.routing', $show) }}">Routing</a> tab to view audio routing.
                    </p>
                </aside>
            </div>
        </div>
    </div>
</x-console-layout>
