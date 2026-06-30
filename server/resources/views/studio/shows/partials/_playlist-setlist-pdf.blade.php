@php
    $hasSetlist = $latestSetlistGeneration !== null;
@endphp

<div class="esb-studio__setlist-pdf mt-4">
    <h3 class="esb-studio__playlist-parts-title">Setlist PDF</h3>

    @if (session('setlist_pdf_generated'))
        <p class="esb-portal__success mt-2" role="status">Setlist PDF generated.</p>
    @endif

    @if (session('setlist_pdf_error'))
        <p class="esb-portal__error mt-2" role="alert">{{ session('setlist_pdf_error') }}</p>
    @endif

    @if ($hasSetlist)
        <p class="esb-studio__card-body mt-2">
            Latest generated {{ $latestSetlistGeneration->generated_at->timezone(config('app.timezone'))->format('j M Y, g:i A') }}.
        </p>

        <div class="esb-studio__setlist-pdf-actions mt-3">
            <a
                href="{{ route('studio.shows.setlist.download', $show) }}"
                class="esb-portal__button esb-portal__button--secondary"
            >
                Download Setlist PDF
            </a>

            @if ($isDirector)
                <form
                    method="POST"
                    action="{{ route('studio.shows.setlist.generate', $show) }}"
                    class="esb-studio__setlist-pdf-generate-form"
                >
                    @csrf
                    <button type="submit" class="esb-portal__button">
                        Regenerate Setlist PDF
                    </button>
                </form>
            @endif
        </div>
    @elseif ($isDirector)
        <p class="esb-studio__card-body mt-2">
            Generate a printable setlist PDF from the current playlist order, including song and playlist notes.
        </p>

        <form
            method="POST"
            action="{{ route('studio.shows.setlist.generate', $show) }}"
            class="esb-studio__setlist-pdf-generate-form mt-3"
        >
            @csrf
            <button
                type="submit"
                class="esb-portal__button"
                @disabled($playlistEntries->isEmpty())
            >
                Generate Setlist PDF
            </button>
        </form>

        @if ($playlistEntries->isEmpty())
            <p class="esb-studio__card-body mt-2">Add songs to the playlist before generating a setlist PDF.</p>
        @endif
    @endif
</div>
