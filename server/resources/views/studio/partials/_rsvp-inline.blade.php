@php
    $useAlpineCard = $useAlpineCard ?? false;
    $alwaysOpen = $alwaysOpen ?? false;
@endphp

<div
    class="esb-studio__rsvp-inline"
    @if (! $alwaysOpen && $useAlpineCard)
        x-show="isRsvpOpen(card)"
        x-cloak
    @elseif (! $alwaysOpen)
        x-show="isRsvpOpen(@js($card))"
        x-cloak
    @endif
>
    <p class="esb-studio__rsvp-inline-title">RSVP</p>

    @if ($useAlpineCard)
        <p class="esb-studio__card-body esb-studio__rsvp-inline-event" x-text="card.show_name + ' · ' + card.date"></p>
    @else
        <p class="esb-studio__card-body esb-studio__rsvp-inline-event">{{ $card['show_name'] }} · {{ $card['date'] }}</p>
    @endif

    <p x-show="!hasMusicianLink" class="esb-portal__error mt-3" role="alert">
        Your account is not linked to a musician profile yet. Ask a director to complete your roster link.
    </p>

    <form
        x-show="hasMusicianLink"
        class="esb-studio__rsvp-inline-form"
        method="POST"
        @if ($useAlpineCard)
            :action="card.rsvp_url"
        @else
            action="{{ $card['rsvp_url'] }}"
        @endif
    >
        @csrf
        <fieldset class="esb-studio__rsvp-options">
            <legend class="sr-only">RSVP response</legend>
            <label class="esb-studio__rsvp-option">
                <input type="radio" name="response" value="yes" x-model="response">
                <span>Yes</span>
            </label>
            <label class="esb-studio__rsvp-option">
                <input type="radio" name="response" value="no" x-model="response">
                <span>No</span>
            </label>
            <label class="esb-studio__rsvp-option">
                <input type="radio" name="response" value="maybe" x-model="response">
                <span>Maybe</span>
            </label>
        </fieldset>

        <div class="mt-4" x-show="showNotesField()" x-cloak>
            <label
                class="esb-portal__label mb-2 block"
                @if ($useAlpineCard)
                    :for="'rsvp-notes-' + card.id"
                @else
                    for="rsvp-notes-{{ $card['id'] }}"
                @endif
            >Notes</label>
            <textarea
                @if ($useAlpineCard)
                    :id="'rsvp-notes-' + card.id"
                @else
                    id="rsvp-notes-{{ $card['id'] }}"
                @endif
                name="notes"
                rows="3"
                class="esb-portal__input esb-studio__band-textarea"
                placeholder="Optional, but encouraged if you cannot attend."
                x-model="notes"
            ></textarea>
        </div>

        <div class="esb-studio__band-form-actions esb-studio__rsvp-inline-actions mt-4">
            <button type="submit" class="esb-portal__button esb-portal__button--primary">
                Save RSVP
            </button>
            @unless ($alwaysOpen)
                <button type="button" class="esb-portal__button esb-portal__button--secondary" @click="closeRsvp()">
                    Cancel
                </button>
            @endunless
        </div>
    </form>
</div>
