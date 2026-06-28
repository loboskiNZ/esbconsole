@php
    $removeConfirm = $part['has_chart']
        ? 'Remove '.$part['name'].' from this song? The linked chart file and record will be preserved but will no longer appear on this song.'
        : 'Remove '.$part['name'].' from this song? The instrument part definition will remain in the catalogue.';
@endphp

<li class="esb-studio__song-part-row">
    @include('studio.shows.partials._instrument-part-pill', [
        'part' => array_merge($part, [
            'song_id' => $song->id,
            'instrument_part_id' => null,
            'song_instrument_part_id' => $part['song_instrument_part_id'],
        ]),
        'showChart' => true,
        'actionable' => false,
    ])

    <form
        method="POST"
        action="{{ route('songs.instrument-parts.destroy', [$song, $part['song_instrument_part_id']]) }}"
        class="esb-studio__song-part-remove-form"
        onsubmit="return confirm(@json($removeConfirm));"
    >
        @csrf
        @method('DELETE')

        @if ($returnTo)
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
        @endif

        <button type="submit" class="esb-studio__song-part-remove-button">
            Remove
        </button>
    </form>
</li>
