@if ($musicLibrarySummary !== null)
    <section
        class="esb-portal__panel esb-studio__card esb-studio__music-library-dashboard"
        aria-labelledby="studio-music-library-dashboard-title"
    >
        <h2 id="studio-music-library-dashboard-title" class="esb-studio__card-title">Music Library</h2>

        <dl class="esb-studio__music-library-dashboard-stats mt-4">
            <div>
                <dt>Songs</dt>
                <dd>{{ number_format($musicLibrarySummary['song_count']) }}</dd>
            </div>
            <div>
                <dt>Archived</dt>
                <dd>{{ number_format($musicLibrarySummary['archived_count']) }}</dd>
            </div>
            <div>
                <dt>Charts</dt>
                <dd>{{ number_format($musicLibrarySummary['chart_count']) }}</dd>
            </div>
            <div>
                <dt>Song Assets</dt>
                <dd>{{ number_format($musicLibrarySummary['song_asset_count']) }}</dd>
            </div>
        </dl>

        <p class="esb-studio__music-library-dashboard-action mt-4">
            <a href="{{ route('songs.index') }}" class="esb-studio__director-tools-link">
                Manage Songs →
            </a>
        </p>
    </section>
@endif
