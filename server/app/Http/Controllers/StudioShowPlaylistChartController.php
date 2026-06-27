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

        return view('studio.shows.chart-upload', [
            'show' => $show,
            'song' => $song,
            'songInstrumentPart' => $part,
            'returnTo' => $redirects->resolve(
                $request->query('return_to'),
                $redirects->showPlaylistReturnPath($show->id),
            ),
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
