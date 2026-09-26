@php
    $card = $item['card'];
    $isDirector = $isDirector ?? false;
    $ticketingHome = $ticketingHome ?? null;
@endphp

<li class="esb-studio__schedule-item" :class="{ 'esb-studio__schedule-item--rsvp-open': isRsvpOpen(@js($card)) }">
    <div class="esb-studio__schedule-item-main">
        <a href="{{ $card['show_url'] }}" class="esb-studio__schedule-item-link">
            <span class="esb-studio__schedule-show">{{ $card['show_name'] }}</span>
            <span class="esb-studio__schedule-meta">{{ $card['type'] }} · {{ $card['status'] }}</span>
            <span class="esb-studio__schedule-meta">{{ $card['date'] }} · {{ $card['time'] }} · {{ $card['location'] }}</span>
            <span class="esb-studio__schedule-rsvp">RSVP: {{ $card['rsvp_label'] }}</span>
        </a>
        @if ($isDirector && is_array($ticketingHome) && $ticketingHome['enabled'])
            <p class="esb-studio__schedule-meta">Ticketing on · Allocated {{ $ticketingHome['allocated'] }} · Remaining {{ $ticketingHome['remaining'] ?? '—' }} · Checked in {{ $ticketingHome['checked_in'] }}</p>
        @endif
    </div>
    <div class="esb-studio__schedule-item-actions">
        @if ($isDirector && is_array($ticketingHome) && $ticketingHome['enabled'])
            <a href="{{ route('studio.performances.ticketing.edit', $card['id']) }}" class="esb-studio__show-pill esb-studio__show-pill--action">Ticketing</a>
            @if ($ticketingHome['door'])
                <a href="{{ route('studio.performances.door', $card['id']) }}" class="esb-studio__show-pill esb-studio__show-pill--action">Door / Check-in</a>
            @endif
        @elseif ($isDirector && is_array($ticketingHome) && $ticketingHome['setup'])
            <a href="{{ route('studio.performances.ticketing.edit', $card['id']) }}" class="esb-studio__show-pill esb-studio__show-pill--action">Set up ticketing</a>
        @endif
        <button
            type="button"
            class="esb-studio__show-pill esb-studio__show-pill--action"
            :class="{ 'esb-studio__show-pill--active': isRsvpOpen(@js($card)) }"
            :aria-expanded="isRsvpOpen(@js($card))"
            @click="toggleRsvp(@js($card))"
        >
            <span x-text="isRsvpOpen(@js($card)) ? 'Close' : 'RSVP'"></span>
        </button>
        <a href="{{ $card['ics_url'] }}" class="esb-studio__show-pill esb-studio__show-pill--action">
            Add to calendar
        </a>
        <a href="{{ $card['show_url'] }}" class="esb-studio__show-pill esb-studio__show-pill--action">
            View
        </a>
    </div>

    @include('studio.partials._rsvp-inline', ['card' => $card])
</li>
