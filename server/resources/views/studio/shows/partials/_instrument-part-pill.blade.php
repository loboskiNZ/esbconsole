@props([
    'part',
    'showChart' => true,
])

<span class="esb-studio__part-pill">
    <span class="esb-studio__part-pill-name">{{ $part['name'] }}</span>
    @if ($showChart)
        <span
            class="esb-studio__part-pill-chart {{ $part['has_chart'] ? 'esb-studio__part-pill-chart--available' : 'esb-studio__part-pill-chart--missing' }}"
            aria-label="{{ $part['name'] }} {{ $part['chart_status_label'] }}"
        >
            <span aria-hidden="true">📄{{ $part['has_chart'] ? '✓' : '✕' }}</span>
            <span class="sr-only">{{ $part['name'] }} {{ $part['chart_status_label'] }}</span>
        </span>
    @endif
</span>
