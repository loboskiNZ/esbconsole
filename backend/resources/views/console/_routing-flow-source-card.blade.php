@props(['card'])

<article @class([
    'vx32-routing-source-card',
    'vx32-routing-source-card--stagebox-a' => $card['key'] === 'stagebox_a',
    'vx32-routing-source-card--stagebox-b' => $card['key'] === 'stagebox_b',
    'vx32-routing-source-card--ableton' => $card['key'] === 'ableton',
    'vx32-routing-source-card--learned' => ($card['status'] ?? '') === 'ready',
    'vx32-routing-source-card--disconnected' => ($card['status'] ?? '') === 'disconnected',
    'vx32-routing-source-card--not-learned' => in_array($card['status'] ?? '', ['not_routed', 'source_offline'], true),
])>
    <header class="vx32-routing-source-card__head">
        <h3 class="vx32-routing-source-card__name">{{ $card['title'] }}</h3>
        <span @class([
            'vx32-routing-source-card__badge',
            'vx32-routing-source-card__badge--learned' => ($card['status'] ?? '') === 'ready',
            'vx32-routing-source-card__badge--disconnected' => ($card['status'] ?? '') === 'disconnected',
            'vx32-routing-source-card__badge--not-learned' => in_array($card['status'] ?? '', ['not_routed', 'source_offline'], true),
        ])>{{ $card['status_label'] }}</span>
    </header>

    <dl class="vx32-routing-source-card__facts">
        <div class="vx32-routing-source-card__fact vx32-routing-source-card__fact--routing">
            <dt>Routing</dt>
            <dd>
                <span class="vx32-routing-source-card__routing-line">{{ $card['routing']['label'] ?? $card['routing_line'] ?? '—' }}</span>
                @if (! empty($card['routing']['line']) && ($card['routing']['state'] ?? '') === 'routed')
                    <span class="vx32-routing-source-card__routing-prefix">{{ $card['routing']['line'] }}</span>
                @elseif (($card['routing']['state'] ?? '') === 'expected' && ! empty($card['routing']['line']))
                    <span class="vx32-routing-source-card__routing-prefix">{{ $card['routing']['line'] }}</span>
                @endif
            </dd>
        </div>
        <div class="vx32-routing-source-card__fact">
            <dt>Connection</dt>
            <dd>{{ $card['connectivity']['label'] ?? $card['connection'] ?? 'Status not monitored yet' }}</dd>
        </div>
        <div class="vx32-routing-source-card__fact">
            <dt>Capacity</dt>
            <dd>{{ $card['capacity'] }}</dd>
        </div>
    </dl>

    <button
        type="button"
        class="vx32-routing-source-card__configure"
        disabled
        title="Not available yet"
    >Configure</button>
</article>
