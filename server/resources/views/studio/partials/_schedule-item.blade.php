@php
    $card = $item['card'];
@endphp

<li class="esb-studio__schedule-item">
    <div class="esb-studio__schedule-item-main">
        <a href="{{ $card['show_url'] }}" class="esb-studio__schedule-item-link">
            <span class="esb-studio__schedule-show">{{ $card['show_name'] }}</span>
            <span class="esb-studio__schedule-meta">{{ $card['type'] }} · {{ $card['status'] }}</span>
            <span class="esb-studio__schedule-meta">{{ $card['date'] }} · {{ $card['time'] }} · {{ $card['location'] }}</span>
            <span class="esb-studio__schedule-rsvp">RSVP: {{ $card['rsvp_label'] }}</span>
        </a>
    </div>
    <div class="esb-studio__schedule-item-actions">
        <button
            type="button"
            class="esb-studio__show-pill esb-studio__show-pill--action"
            @click="openRsvp(@js($card))"
        >
            RSVP
        </button>
        <a href="{{ $card['ics_url'] }}" class="esb-studio__show-pill esb-studio__show-pill--action">
            Add to calendar
        </a>
        <a href="{{ $card['show_url'] }}" class="esb-studio__show-pill esb-studio__show-pill--action">
            View
        </a>
    </div>
</li>
