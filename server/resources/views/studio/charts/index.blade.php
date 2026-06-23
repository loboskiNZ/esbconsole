@extends('layouts.portal')

@section('title', 'Charts — The Studio')

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio</p>
            <h1 class="esb-portal__title">Charts</h1>
            <p class="esb-studio__card-body mt-2">Your rehearsal library</p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ route('studio') }}" class="esb-studio__back-link">← Back to Studio</a>
            </div>

            @if (! $hasInstrumentAssignments)
                <section class="esb-portal__panel esb-studio__card">
                    <p class="esb-studio__card-body">
                        No matching charts are available for your instruments yet.
                    </p>
                </section>
            @elseif ($songs->isEmpty())
                <section class="esb-portal__panel esb-studio__card">
                    <p class="esb-studio__card-body">
                        No matching charts are available for your instruments yet.
                    </p>
                </section>
            @else
                <section class="esb-portal__panel esb-studio__card">
                    <h2 class="esb-studio__card-title">Songs</h2>
                    <ul class="esb-studio__charts-song-list mt-4">
                        @foreach ($songs as $song)
                            @php($metadata = $songMetadataById[$song->id] ?? [])
                            <li
                                class="esb-studio__charts-song-item"
                                style="--esb-song-mood-accent: {{ $metadata['mood_accent_colour_hex'] ?? \App\Support\StudioSongMetadata::DEFAULT_MOOD_ACCENT }};"
                            >
                                <a href="{{ route('studio.charts.show', $song) }}" class="esb-studio__charts-song-link">
                                    <span class="esb-studio__charts-song-name">{{ $song->name }}</span>
                                    <span class="esb-studio__charts-song-meta">
                                        {{ $song->my_chart_count }} {{ str('chart')->plural($song->my_chart_count) }}
                                        @if ($metadata['bpm'] ?? null)
                                            · {{ $metadata['bpm'] }} BPM
                                        @endif
                                        @if ($metadata['time_signature'] ?? null)
                                            · {{ $metadata['time_signature'] }}
                                        @endif
                                        @if ($metadata['musical_key'] ?? null)
                                            · {{ $metadata['musical_key'] }}
                                        @endif
                                    </span>
                                </a>
                                <span class="esb-studio__song-mood-pill esb-studio__song-mood-pill--compact">
                                    {{ $metadata['mood_label'] ?? \App\Support\StudioSongMetadata::DEFAULT_MOOD_LABEL }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>
    </main>
@endsection
