<dl class="esb-studio__playlist-inline-summary mt-4" id="playlist-inline-summary" data-playlist-summary>
    <div>
        <dt>Songs</dt>
        <dd data-playlist-summary-songs>{{ number_format($playlistSummary['song_count']) }}</dd>
    </div>
    <div>
        <dt>Instrument parts</dt>
        <dd data-playlist-summary-parts>{{ number_format($playlistSummary['instrument_part_count']) }}</dd>
    </div>
    <div>
        <dt>Charts</dt>
        <dd data-playlist-summary-charts>{{ number_format($playlistSummary['charts_count']) }}</dd>
    </div>
    <div>
        <dt>Estimated duration</dt>
        <dd data-playlist-summary-duration>{{ $playlistSummary['estimated_duration_label'] }}</dd>
    </div>
</dl>
