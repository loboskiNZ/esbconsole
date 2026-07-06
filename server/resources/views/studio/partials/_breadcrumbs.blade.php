@php
    /** @var list<array{label: string, url?: string|null}> $breadcrumbs */
@endphp

@if ($breadcrumbs !== [])
    <nav class="esb-studio__breadcrumbs" aria-label="Breadcrumb">
        <ol class="esb-studio__breadcrumbs-list">
            @foreach ($breadcrumbs as $index => $crumb)
                @php
                    $isLast = $index === count($breadcrumbs) - 1;
                    $label = $crumb['label'] ?? '';
                    $url = $crumb['url'] ?? null;
                @endphp
                <li class="esb-studio__breadcrumbs-item">
                    @if (! $isLast && filled($url))
                        <a href="{{ $url }}" class="esb-studio__breadcrumbs-link">{{ $label }}</a>
                    @else
                        <span class="esb-studio__breadcrumbs-current" @if ($isLast) aria-current="page" @endif>{{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
