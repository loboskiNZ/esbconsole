<?php

namespace App\Http\Controllers;

use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Models\Show;
use App\Services\StudioShowPlaylistChartService;
use App\Support\SafeInternalRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudioShowPlaylistChartController extends Controller
{
    public function create(
        Request $request,
        Show $show,
        Song $song,
        SongInstrumentPart $songInstrumentPart,
        StudioShowPlaylistChartService $charts,
        SafeInternalRedirect $redirects,
    ): View {
        $part = $charts->songInstrumentPartForShowPlaylist($show, $song, $songInstrumentPart);

        abort_if($part->chart_id !== null, 404);

        $showId = (int) $show->id;
        $songId = (int) $song->id;
        $partId = (int) $part->id;

        return view('studio.shows.chart-upload', [
            'show' => $show,
            'pageTitle' => 'Upload Chart — '.$song->name,
            'songName' => $song->name,
            'partName' => $part->instrumentPart?->name ?? 'Instrument part',
            'returnTo' => $redirects->resolve(
                $request->query('return_to'),
                $redirects->showPlaylistReturnPath($showId),
            ),
            'uploadAction' => route('studio.shows.playlist.chart.upload.store', [
                'show' => $showId,
                'song' => $songId,
                'songInstrumentPart' => $partId,
            ]),
        ]);
    }

    public function store(
        Request $request,
        Show $show,
        Song $song,
        SongInstrumentPart $songInstrumentPart,
        StudioShowPlaylistChartService $charts,
        SafeInternalRedirect $redirects,
    ): RedirectResponse {
        $part = $charts->songInstrumentPartForShowPlaylist($show, $song, $songInstrumentPart);

        abort_if($part->chart_id !== null, 404);

        $validated = $request->validate([
            'chart' => ['required', 'file', 'mimes:pdf', 'max:20480'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $charts->uploadChartForSongInstrumentPart($part, $validated['chart']);

        return redirect()
            ->to($redirects->resolve(
                $validated['return_to'] ?? null,
                $redirects->showPlaylistReturnPath($show->id),
            ))
            ->with('playlist_updated', true);
    }
}
