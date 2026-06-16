@php
    $production = $routingDetail['production'];
    $inputs = $routingDetail['input_sources'];
    $outputs = $routingDetail['outputs'];
    $allocationGroups = $inputs['channel_allocation']['groups'];
@endphp

<section class="vx32-routing-detail" aria-labelledby="routing-detail-title">
    <h2 id="routing-detail-title" class="vx32-routing-detail__sr-title">Configuration Detail</h2>

    <div class="vx32-routing-detail__grid">
        {{-- Card A: Current Production Configuration --}}
        <article class="vx32-routing-detail__panel vx32-routing-detail__panel--production">
            <header class="vx32-routing-detail__production-head">
                <h3 class="vx32-routing-detail__config-name">{{ $production['name'] }}</h3>
                <p @class([
                    'vx32-routing-detail__type-badge',
                    'vx32-routing-detail__type-badge--learned' => $production['type']['state'] === 'learned',
                    'vx32-routing-detail__type-badge--not-learned' => $production['type']['state'] !== 'learned',
                ])>{{ $production['type']['label'] }}</p>
            </header>

            <ul class="vx32-routing-detail__status-grid">
                @foreach ($production['status_grid'] as $tile)
                    <li @class([
                        'vx32-routing-detail__status-tile',
                        'vx32-routing-detail__status-tile--learned' => in_array($tile['status_state'], ['learned', 'partial', 'routed', 'expected'], true),
                        'vx32-routing-detail__status-tile--not-learned' => in_array($tile['status_state'], ['not_learned', 'not_routed'], true),
                    ])>
                        <span class="vx32-routing-detail__status-tile-label">{{ $tile['label'] }}</span>
                        <span class="vx32-routing-detail__status-tile-value">{{ $tile['status_label'] }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="vx32-routing-detail__section">
                <h4 class="vx32-routing-detail__section-label">Presets</h4>
                <ul class="vx32-routing-detail__future-list">
                    @foreach ($production['future_configurations'] as $configuration)
                        <li @class([
                            'vx32-routing-detail__future-item',
                            'vx32-routing-detail__future-item--current' => $configuration['is_current'],
                        ])>{{ $configuration['name'] }}</li>
                    @endforeach
                </ul>
            </div>
        </article>

        {{-- Card B: Input Sources --}}
        <article class="vx32-routing-detail__panel vx32-routing-detail__panel--inputs">
            <h3 class="vx32-routing-detail__panel-title">{{ $inputs['title'] }}</h3>

            <div class="vx32-routing-detail__input-cards">
                @foreach ($inputs['cards'] as $card)
                    <div @class([
                        'vx32-routing-detail__input-card',
                        'vx32-routing-detail__input-card--stagebox-a' => $card['key'] === 'stagebox_a',
                        'vx32-routing-detail__input-card--stagebox-b' => $card['key'] === 'stagebox_b',
                        'vx32-routing-detail__input-card--ableton' => $card['key'] === 'ableton',
                    ])>
                        <header class="vx32-routing-detail__input-card-head">
                            <h4 class="vx32-routing-detail__input-card-title">{{ $card['title'] }}</h4>
                            <span @class([
                                'vx32-routing-detail__input-routing-pill',
                                'vx32-routing-detail__input-routing-pill--routed' => ($card['routing_pill']['state'] ?? '') === 'routed',
                                'vx32-routing-detail__input-routing-pill--not-routed' => ($card['routing_pill']['state'] ?? '') === 'not_routed',
                                'vx32-routing-detail__input-routing-pill--expected' => ($card['routing_pill']['state'] ?? '') === 'expected',
                            ])>{{ $card['routing_pill']['label'] }}</span>
                        </header>

                        <dl class="vx32-routing-detail__input-facts">
                            <div class="vx32-routing-detail__input-fact">
                                <dt>Connection</dt>
                                <dd>{{ $card['connectivity']['label'] }}</dd>
                            </div>
                            <div class="vx32-routing-detail__input-fact">
                                <dt>Channels</dt>
                                <dd>{{ $card['channel_range'] }}</dd>
                            </div>
                            <div class="vx32-routing-detail__input-fact">
                                <dt>Result</dt>
                                <dd @class([
                                    'vx32-routing-detail__input-result--ready' => ($card['result']['state'] ?? '') === 'ready',
                                    'vx32-routing-detail__input-result--disconnected' => ($card['result']['state'] ?? '') === 'disconnected',
                                    'vx32-routing-detail__input-result--source-offline' => ($card['result']['state'] ?? '') === 'source_offline',
                                    'vx32-routing-detail__input-result--not-routed' => ($card['result']['state'] ?? '') === 'not_routed',
                                ])>{{ $card['result']['label'] }}</dd>
                            </div>
                        </dl>

                        <button type="button" class="vx32-routing-detail__configure" disabled title="Not available yet">Configure</button>
                    </div>
                @endforeach
            </div>

            <div class="vx32-routing-detail__allocation">
                <h4 class="vx32-routing-detail__allocation-title">{{ $inputs['channel_allocation']['title'] }}</h4>

                <div class="vx32-routing-detail__allocation-layout">
                    @foreach ($allocationGroups as $group)
                        @php
                            $zoneSpan = $group['end'] - $group['start'] + 1;
                        @endphp
                        <div class="vx32-routing-detail__allocation-block" style="--zone-span: {{ $zoneSpan }};">
                            <div @class([
                                'vx32-routing-detail__strip-zone',
                                'vx32-routing-detail__strip-zone--stagebox_a' => $group['key'] === 'stagebox_a',
                                'vx32-routing-detail__strip-zone--stagebox_b' => $group['key'] === 'stagebox_b',
                                'vx32-routing-detail__strip-zone--ableton' => $group['key'] === 'ableton',
                            ])>
                                <span class="vx32-routing-detail__strip-zone-label">{{ $group['label'] }}</span>
                                <span class="vx32-routing-detail__strip-zone-detail">{{ $group['detail'] }}</span>
                            </div>

                            <div class="vx32-routing-detail__console-strip">
                                @foreach ($inputs['channel_allocation']['channels'] as $channel)
                                    @if ($channel['number'] < $group['start'] || $channel['number'] > $group['end'])
                                        @continue
                                    @endif
                                    @php
                                        $stripGroup = $channel['group'] ?? (
                                            $channel['number'] <= 16
                                                ? 'stagebox_a'
                                                : ($channel['number'] <= 24 ? 'stagebox_b' : 'ableton')
                                        );
                                        $channelLabel = $channel['name'] !== ''
                                            ? $channel['name']
                                            : ($stripGroup === 'ableton' ? 'A'.($channel['number'] - 24) : '—');
                                    @endphp
                                    <div @class([
                                        'vx32-routing-detail__fader-tile',
                                        'vx32-routing-detail__fader-tile--stagebox_a' => $stripGroup === 'stagebox_a',
                                        'vx32-routing-detail__fader-tile--stagebox_b' => $stripGroup === 'stagebox_b',
                                        'vx32-routing-detail__fader-tile--ableton' => $stripGroup === 'ableton',
                                        'vx32-routing-detail__fader-tile--local' => $stripGroup === 'local',
                                        'vx32-routing-detail__fader-tile--learned' => $channel['state'] === 'learned',
                                    ]) title="{{ $channel['label'] }} · {{ $channelLabel }}">
                                        <span class="vx32-routing-detail__fader-num">{{ $channel['number'] }}</span>
                                        <div class="vx32-routing-detail__fader-namestrip">
                                            <span class="vx32-routing-detail__fader-name">{{ $channelLabel }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <ul class="vx32-routing-detail__allocation-legend">
                    @foreach ($inputs['channel_allocation']['legend'] as $item)
                        <li class="vx32-routing-detail__legend-item vx32-routing-detail__legend-item--{{ $item['key'] }}">{{ $item['label'] }}</li>
                    @endforeach
                </ul>
            </div>
        </article>

        {{-- Card C: Outputs --}}
        <article class="vx32-routing-detail__panel vx32-routing-detail__panel--outputs">
            <h3 class="vx32-routing-detail__panel-title">{{ $outputs['title'] }}</h3>

            <div class="vx32-routing-detail__output-section">
                <h4 class="vx32-routing-detail__output-section-title">{{ $outputs['foh']['title'] }}</h4>
                <ul class="vx32-routing-detail__output-lines">
                    @foreach ($outputs['foh']['lines'] as $line)
                        <li class="vx32-routing-detail__output-line">
                            <strong>{{ $line['label'] }}</strong>
                            {{ str_replace(' (Suggested)', '', $line['route']) }}
                            @if ($line['source'] !== 'Not learned')
                                <span class="vx32-routing-detail__output-source">{{ $line['source'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
                <button type="button" class="vx32-routing-detail__configure" disabled title="Not available yet">Configure</button>
            </div>

            <div class="vx32-routing-detail__output-section">
                <h4 class="vx32-routing-detail__output-section-title">{{ $outputs['out_1_16']['title'] ?? 'Out 1–16' }}</h4>
                <p class="vx32-routing-detail__spare-summary">{{ $outputs['out_1_16']['summary'] ?? 'Not learned' }}</p>
                @if (! empty($outputs['out_1_16']['blocks']))
                    <ul class="vx32-routing-detail__output-lines">
                        @foreach ($outputs['out_1_16']['blocks'] as $block)
                            <li class="vx32-routing-detail__output-line">
                                <strong>{{ $block['output_range'] }}</strong>
                                {{ $block['source_range'] }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="vx32-routing-detail__output-section">
                <h4 class="vx32-routing-detail__output-section-title">Spare outputs</h4>
                <p class="vx32-routing-detail__spare-summary">{{ $outputs['spare']['summary'] }}</p>
                <button type="button" class="vx32-routing-detail__configure vx32-routing-detail__configure--info" disabled title="Not available yet">View All Outputs</button>
            </div>
        </article>
    </div>
</section>
