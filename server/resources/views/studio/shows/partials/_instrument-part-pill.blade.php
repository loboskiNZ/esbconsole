@props([
    'part',
    'showChart' => true,
    'actionable' => false,
    'show' => null,
    'isDirector' => false,
])

@php
    $tag = 'span';
    $attributes = ['class' => 'esb-studio__part-pill'];

    if ($actionable) {
        if ($part['has_chart'] && ! empty($part['chart_id'])) {
            $tag = 'a';
            $attributes = [
                'href' => route('studio.charts.file', $part['chart_id']),
                'class' => 'esb-studio__part-pill esb-studio__part-pill--link esb-studio__part-pill--available',
                'target' => '_blank',
                'rel' => 'noopener',
                'aria-label' => $part['name'].' chart available — open chart',
            ];
        } elseif (! $part['has_chart'] && $isDirector && $show !== null) {
            $tag = 'a';
            $attributes = [
                'href' => route('studio.shows.playlist.chart.upload.create', [
                    'show' => $show,
                    'song' => $part['song_id'],
                    'songInstrumentPart' => $part['song_instrument_part_id'],
                    'return_to' => app(\App\Support\SafeInternalRedirect::class)->showPlaylistReturnPath($show->id),
                ]),
                'class' => 'esb-studio__part-pill esb-studio__part-pill--link esb-studio__part-pill--missing',
                'aria-label' => $part['name'].' chart missing — upload chart',
            ];
        }
    }
@endphp

<{{ $tag }} @foreach ($attributes as $attribute => $value) {{ $attribute }}="{{ $value }}" @endforeach>
    <span class="esb-studio__part-pill-name">{{ $part['name'] }}</span>
    @if ($showChart)
        <span
            class="esb-studio__part-pill-chart {{ $part['has_chart'] ? 'esb-studio__part-pill-chart--available' : 'esb-studio__part-pill-chart--missing' }}"
            @if ($tag === 'span')
                aria-label="{{ $part['name'] }} {{ $part['chart_status_label'] }}"
            @else
                aria-hidden="true"
            @endif
        >
            <span aria-hidden="true">📄{{ $part['has_chart'] ? '✓' : '✕' }}</span>
            @if ($tag === 'span')
                <span class="sr-only">{{ $part['name'] }} {{ $part['chart_status_label'] }}</span>
            @endif
        </span>
    @endif
</{{ $tag }}>
