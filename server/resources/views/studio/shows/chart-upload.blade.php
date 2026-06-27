@extends('layouts.portal')

@section('title', 'Upload Chart — '.$song->name)

@section('body-attributes')
    class="esb-portal esb-portal--studio antialiased"
@endsection

@section('content')
    <main class="esb-studio__shell relative z-10 flex min-h-dvh w-full flex-col">
        <header class="esb-studio__chrome-header">
            <p class="esb-portal__eyebrow mb-2">ESB Studio · Show Playlist</p>
            <h1 class="esb-portal__title">Upload chart</h1>
            <p class="esb-studio__card-body mt-2">
                {{ $song->name }} · {{ $songInstrumentPart->instrumentPart?->name ?? 'Instrument part' }}
            </p>
        </header>

        <div class="esb-studio__shell-body">
            <div class="esb-studio__charts-nav mb-4">
                <a href="{{ $returnTo }}" class="esb-studio__back-link">← Back to show playlist</a>
            </div>

            <form
                class="esb-portal__panel esb-studio__card esb-studio__show-form"
                method="POST"
                action="{{ route('studio.shows.playlist.chart.upload.store', [$show, $song, $songInstrumentPart]) }}"
                enctype="multipart/form-data"
            >
                @csrf
                <input type="hidden" name="return_to" value="{{ $returnTo }}">

                @if ($errors->any())
                    <div class="esb-portal__error mb-6" role="alert">
                        <ul class="esb-studio__users-error-list">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="esb-studio__card-body">
                    Upload a PDF chart for this song and instrument part. The chart will be linked only to
                    {{ $songInstrumentPart->instrumentPart?->name ?? 'this part' }} on {{ $song->name }}.
                </p>

                <div class="esb-studio__band-form-grid mt-4">
                    <div>
                        <label class="esb-portal__label mb-2 block" for="chart-file">Chart PDF</label>
                        <input
                            id="chart-file"
                            name="chart"
                            type="file"
                            accept="application/pdf,.pdf"
                            class="esb-portal__input"
                            required
                        >
                    </div>
                </div>

                <div class="esb-studio__band-form-actions mt-6">
                    <button type="submit" class="esb-portal__button esb-portal__button--primary">
                        Upload chart
                    </button>
                </div>
            </form>
        </div>
    </main>
@endsection
