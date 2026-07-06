<header class="esb-studio__chrome-header">
    <p class="esb-portal__eyebrow mb-2">
        <a href="{{ route('studio') }}" class="esb-studio__brand-link">ESB Studio</a>
    </p>

    @if (! empty($breadcrumbs))
        @include('studio.partials._breadcrumbs', ['breadcrumbs' => $breadcrumbs])
    @endif

    <h1 class="esb-portal__title">{{ $pageTitle }}</h1>

    @if (! empty($pageLead))
        <p class="esb-studio__card-body mt-2">{{ $pageLead }}</p>
    @endif
</header>
