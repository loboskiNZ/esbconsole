<?php

namespace App\Http\Controllers;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Services\StudioChartAccessService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioChartsController extends Controller
{
    public function index(StudioChartAccessService $chartAccess): View
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->person_id !== null, 403);

        $person = $user->person()->with('instruments')->firstOrFail();
        $songs = $chartAccess->songsForPerson($person);
        $hasInstrumentAssignments = $person->instruments->isNotEmpty();

        return view('studio.charts.index', [
            'user' => $user,
            'person' => $person,
            'songs' => $songs,
            'hasInstrumentAssignments' => $hasInstrumentAssignments,
        ]);
    }

    public function show(Song $song, StudioChartAccessService $chartAccess): View
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->person_id !== null, 403);

        $person = $user->person()->with('instruments')->firstOrFail();
        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);

        $chartLinks = $chartAccess->chartLinksForPersonAndSong($person, $song);

        return view('studio.charts.show', [
            'user' => $user,
            'person' => $person,
            'song' => $song,
            'chartLinks' => $chartLinks,
            'hasInstrumentAssignments' => $person->instruments->isNotEmpty(),
        ]);
    }

    public function chartFile(Chart $chart, StudioChartAccessService $chartAccess): Response|StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->person_id !== null, 403);

        $person = $user->person()->with('instruments')->firstOrFail();

        if (! $chartAccess->personCanAccessChart($person, $chart)) {
            abort(403);
        }

        $disk = (string) config('portal.library_chart_disk', 'library');
        $path = $chart->storage_reference;

        try {
            $diskAvailable = $path !== null && Storage::disk($disk)->exists($path);
        } catch (\Throwable) {
            $diskAvailable = false;
        }

        if (! $diskAvailable) {
            abort(404);
        }

        $filename = $chart->original_filename ?: basename($path);

        return Storage::disk($disk)->response($path, $filename, [
            'Content-Type' => $chart->mime_type ?: 'application/pdf',
        ]);
    }
}
