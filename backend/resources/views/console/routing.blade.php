@php
    $flow = $routingFlow;
@endphp

<x-console-layout>
    <div class="vx32-console">
        @include('console._console-header', [
            'show' => $show,
            'consoleType' => $consoleType,
            'workspaceMode' => $workspaceMode,
            'summary' => $summary,
            'activeTab' => 'routing',
        ])

        <div class="vx32-routing-workspace">
            <header class="vx32-routing-workspace__header">
                <div class="vx32-routing-workspace__header-left">
                    <span class="vx32-routing-workspace__context">ESB Console</span>
                    <div class="vx32-routing-workspace__title-row">
                        <h1 class="vx32-routing-workspace__title">Audio Routing</h1>
                        @php
                            $workspaceRoutingState = $flow['routing_state']['state'] ?? ($flow['console']['status'] ?? 'not_learned');
                            $workspaceRoutingLabel = $flow['routing_state']['label'] ?? match ($workspaceRoutingState) {
                                'learned' => 'Routing from console',
                                'partial' => 'Partial routing',
                                'ok' => 'Channel routing OK',
                                'needs_attention' => 'Routing needs attention',
                                default => 'Awaiting console routing learn',
                            };
                            if (! empty($routingDetail['production']['learned_meta']['primary']) && $workspaceRoutingState !== 'not_learned') {
                                $workspaceRoutingLabel = $routingDetail['production']['learned_meta']['primary'];
                            }
                        @endphp
                        <span @class([
                            'vx32-routing-workspace__routing-state',
                            'vx32-routing-workspace__routing-state--learned' => $workspaceRoutingState === 'learned',
                            'vx32-routing-workspace__routing-state--partial' => $workspaceRoutingState === 'partial',
                            'vx32-routing-workspace__routing-state--suggested' => $workspaceRoutingState === 'suggested',
                            'vx32-routing-workspace__routing-state--not-learned' => $workspaceRoutingState === 'not_learned',
                        ])>{{ $workspaceRoutingLabel }}</span>
                    </div>
                </div>
                <div class="vx32-routing-workspace__header-actions" aria-label="Primary actions">
                    @if (! empty($routingDetail['production']['learned_meta']['secondary']))
                        <span class="vx32-routing-workspace__sync-hint">{{ $routingDetail['production']['learned_meta']['secondary'] }}</span>
                    @endif
                </div>
            </header>

            <div class="vx32-routing-workspace__content">
                <section class="vx32-routing-flow" aria-labelledby="routing-flow-title">
                    <h2 id="routing-flow-title" class="vx32-routing-flow__title">Routing Flow</h2>

                    <div class="vx32-routing-flow__map">
                        {{-- Sources --}}
                        <div class="vx32-routing-flow__zone vx32-routing-flow__zone--sources">
                            <h3 class="vx32-routing-flow__zone-label">Sources</h3>
                            <div class="vx32-routing-flow__source-row">
                                @foreach ($flow['sources'] as $card)
                                    @include('console._routing-flow-source-card', ['card' => $card])
                                @endforeach
                            </div>
                        </div>

                        {{-- Connector: Sources → Console (left to right) --}}
                        <div class="vx32-routing-flow__connector vx32-routing-flow__connector--in" aria-hidden="true">
                            <svg class="vx32-routing-flow__svg" viewBox="0 0 120 100" preserveAspectRatio="xMidYMid meet" role="presentation">
                                <defs>
                                    <marker id="routing-flow-arrow-in" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                                        <path d="M0,0 L8,4 L0,8 Z" fill="rgb(34 197 94)" />
                                    </marker>
                                </defs>
                                <path class="vx32-routing-flow__path vx32-routing-flow__path--stagebox-a" d="M4,18 H 52 V 50 H 88" />
                                <path class="vx32-routing-flow__path vx32-routing-flow__path--stagebox-b" d="M4,50 H 88" />
                                <path class="vx32-routing-flow__path vx32-routing-flow__path--ableton" d="M4,82 H 52 V 50 H 88" />
                                <path class="vx32-routing-flow__path vx32-routing-flow__path--merge" d="M88,50 H 116" marker-end="url(#routing-flow-arrow-in)" />
                            </svg>
                        </div>

                        {{-- Console processing --}}
                        <div class="vx32-routing-flow__zone vx32-routing-flow__zone--console">
                            <h3 class="vx32-routing-flow__zone-label">Console Processing</h3>
                            <article @class([
                                'vx32-routing-console-card',
                                'vx32-routing-console-card--' . $flow['console']['status'],
                                'vx32-routing-console-card--learned' => in_array($flow['console']['status'], ['learned', 'ok'], true),
                                'vx32-routing-console-card--partial' => in_array($flow['console']['status'], ['partial', 'needs_attention'], true),
                                'vx32-routing-console-card--not-learned' => $flow['console']['status'] === 'not_learned',
                            ])>
                                <header class="vx32-routing-console-card__head">
                                    <h4 class="vx32-routing-console-card__title">{{ $flow['console']['title'] }}</h4>
                                    <span @class([
                                        'vx32-routing-source-card__badge',
                                        'vx32-routing-source-card__badge--learned' => in_array($flow['console']['status'], ['learned', 'ok'], true),
                                        'vx32-routing-source-card__badge--suggested' => $flow['console']['status'] === 'suggested',
                                        'vx32-routing-source-card__badge--not-learned' => in_array($flow['console']['status'], ['not_learned', 'needs_attention'], true),
                                        'vx32-routing-source-card__badge--partial' => $flow['console']['status'] === 'partial',
                                    ])>{{ $flow['console']['status_label'] }}</span>
                                </header>
                                <p class="vx32-routing-console-card__range">{{ $flow['console']['channel_range'] }}</p>
                                <ul class="vx32-routing-console-card__summaries">
                                    @foreach ($flow['console']['summaries'] as $summaryLine)
                                        <li @class([
                                            'vx32-routing-console-card__summary',
                                            'vx32-routing-console-card__summary--' . ($summaryLine['key'] ?? 'unknown'),
                                            'vx32-routing-console-card__summary--' . ($summaryLine['status'] ?? 'not_routed'),
                                        ])>
                                            <span class="vx32-routing-console-card__summary-name">{{ $summaryLine['source'] }}</span>
                                            <span class="vx32-routing-console-card__summary-routing">{{ $summaryLine['routing_label'] ?? $summaryLine['line'] ?? '—' }}</span>
                                            @if (! empty($summaryLine['channel_range']))
                                                <span class="vx32-routing-console-card__summary-channels">{{ $summaryLine['channel_range'] }}</span>
                                            @endif
                                            @if (! empty($summaryLine['result_label']) && ($summaryLine['result_label'] ?? '') !== '—')
                                                <span class="vx32-routing-console-card__summary-result">{{ $summaryLine['result_label'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </article>
                        </div>

                        {{-- Connector: Console → Destinations (left to right) --}}
                        <div class="vx32-routing-flow__connector vx32-routing-flow__connector--out" aria-hidden="true">
                            <svg class="vx32-routing-flow__svg" viewBox="0 0 120 100" preserveAspectRatio="xMidYMid meet" role="presentation">
                                <defs>
                                    <marker id="routing-flow-arrow-foh" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                                        <path d="M0,0 L8,4 L0,8 Z" fill="rgb(250 204 21)" />
                                    </marker>
                                    <marker id="routing-flow-arrow-iems" markerWidth="8" markerHeight="8" refX="7" refY="4" orient="auto">
                                        <path d="M0,0 L8,4 L0,8 Z" fill="rgb(56 189 248)" />
                                    </marker>
                                </defs>
                                <path class="vx32-routing-flow__path vx32-routing-flow__path--trunk" d="M4,50 H 36" />
                                <path class="vx32-routing-flow__path vx32-routing-flow__path--foh vx32-routing-flow__path--dashed" d="M36,50 V 28 H 116" marker-end="url(#routing-flow-arrow-foh)" />
                                <path class="vx32-routing-flow__path vx32-routing-flow__path--iems vx32-routing-flow__path--dashed" d="M36,50 V 72 H 116" marker-end="url(#routing-flow-arrow-iems)" />
                            </svg>
                        </div>

                        {{-- Destinations --}}
                        <div class="vx32-routing-flow__zone vx32-routing-flow__zone--destinations">
                            <h3 class="vx32-routing-flow__zone-label">Destinations</h3>
                            <div class="vx32-routing-flow__destination-row">
                                @foreach ($flow['destinations'] as $destination)
                                    <article @class([
                                        'vx32-routing-dest-card',
                                        'vx32-routing-dest-card--foh' => $destination['key'] === 'foh',
                                        'vx32-routing-dest-card--iems' => $destination['key'] === 'iems',
                                        'vx32-routing-dest-card--learned' => $destination['status'] === 'learned',
                                        'vx32-routing-dest-card--partial' => $destination['status'] === 'partial',
                                        'vx32-routing-dest-card--suggested' => $destination['status'] === 'suggested',
                                        'vx32-routing-dest-card--not-learned' => $destination['status'] === 'not_learned',
                                    ])>
                                        <header class="vx32-routing-dest-card__head">
                                            <h4 class="vx32-routing-dest-card__title">{{ $destination['title'] }}</h4>
                                            <span @class([
                                                'vx32-routing-source-card__badge',
                                                'vx32-routing-source-card__badge--learned' => in_array($destination['status'], ['learned', 'partial'], true),
                                                'vx32-routing-source-card__badge--suggested' => $destination['status'] === 'suggested',
                                                'vx32-routing-source-card__badge--not-learned' => $destination['status'] === 'not_learned',
                                            ])>{{ $destination['status_label'] }}</span>
                                        </header>

                                        @if (! empty($destination['summary']))
                                            <p class="vx32-routing-dest-card__summary">{{ $destination['summary'] }}</p>
                                        @endif

                                        @if ($destination['key'] === 'iems' && ! empty($destination['columns']))
                                            @include('console._routing-iem-bus-grid', ['columns' => $destination['columns']])
                                        @else
                                            <ul class="vx32-routing-dest-card__lines">
                                                @foreach ($destination['lines'] as $line)
                                                    <li>
                                                        @if (is_array($line))
                                                            <strong>{{ $line['label'] }}</strong> {{ $line['value'] }}
                                                        @else
                                                            {{ $line }}
                                                        @endif
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        <button
                                            type="button"
                                            class="vx32-routing-source-card__configure"
                                            disabled
                                            title="Not available yet"
                                        >Configure</button>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </section>

                @include('console._routing-detail-row')

                @include('console._routing-bottom-row')
            </div>
        </div>
    </div>
</x-console-layout>
