@extends('layouts.portal')

@section('title', $song->name.' — Charts')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio · Charts</p>
            <h1 class="esb-portal__title">{{ $song->name }}</h1>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio.charts.index') }}" class="esb-studio__back-link">← All songs</a>
            </div>

            <section class="esb-portal__panel esb-studio__card">
                <h2 class="esb-studio__card-title">My Charts</h2>

                @if (! $hasInstrumentAssignments)
                    <p class="esb-studio__card-body mt-3">
                        No matching charts are available for your instruments yet.
                    </p>
                @elseif ($chartLinks->isEmpty())
                    <p class="esb-studio__card-body mt-3">
                        No matching charts are available for your instruments yet.
                    </p>
                @else
                    <ul class="esb-studio__charts-list mt-4">
                        @foreach ($chartLinks as $link)
                            @php($chart = $link->chart)
                            <li class="esb-studio__charts-item">
                                <div class="esb-studio__charts-item-body">
                                    <p class="esb-studio__charts-part">{{ $link->instrumentPart?->name }}</p>
                                    <p class="esb-studio__charts-title">{{ $chart?->title ?? 'Chart' }}</p>
                                    @if ($chart?->original_filename)
                                        <p class="esb-studio__charts-filename">{{ $chart->original_filename }}</p>
                                    @endif
                                </div>
                                @if ($chart)
                                    <a
                                        href="{{ route('studio.charts.file', $chart) }}"
                                        class="esb-portal__button esb-portal__button--secondary esb-studio__charts-view-link"
                                        target="_blank"
                                        rel="noopener"
                                    >
                                        View
                                    </a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </main>
@endsection
