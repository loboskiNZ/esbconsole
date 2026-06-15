@props(['card'])

<article @class([
    'vx32-routing-source-card',
    'vx32-routing-source-card--stagebox-a' => $card['key'] === 'stagebox_a',
    'vx32-routing-source-card--stagebox-b' => $card['key'] === 'stagebox_b',
    'vx32-routing-source-card--ableton' => $card['key'] === 'ableton',
    'vx32-routing-source-card--learned' => $card['status'] === 'learned',
    'vx32-routing-source-card--suggested' => $card['status'] === 'suggested',
    'vx32-routing-source-card--not-learned' => $card['status'] === 'not_learned',
])>
    <header class="vx32-routing-source-card__head">
        <h3 class="vx32-routing-source-card__name">{{ $card['title'] }}</h3>
        <span @class([
            'vx32-routing-source-card__badge',
            'vx32-routing-source-card__badge--learned' => $card['status'] === 'learned',
            'vx32-routing-source-card__badge--suggested' => $card['status'] === 'suggested',
            'vx32-routing-source-card__badge--not-learned' => $card['status'] === 'not_learned',
        ])>{{ $card['status_label'] }}</span>
    </header>

    <dl class="vx32-routing-source-card__facts">
        <div class="vx32-routing-source-card__fact">
            <dt>Connection</dt>
            <dd>{{ $card['connection'] }}</dd>
        </div>
        <div class="vx32-routing-source-card__fact">
            <dt>Capacity</dt>
            <dd>{{ $card['capacity'] }}</dd>
        </div>
        <div class="vx32-routing-source-card__fact vx32-routing-source-card__fact--routing">
            <dt>Routing</dt>
            <dd>
                <span class="vx32-routing-source-card__routing-prefix">{{ $card['routing_prefix'] }}:</span>
                <span class="vx32-routing-source-card__routing-line">{{ $card['routing_line'] }}</span>
            </dd>
        </div>
    </dl>

    <button
        type="button"
        class="vx32-routing-source-card__configure"
        disabled
        title="Not available yet"
    >Configure</button>
</article>
