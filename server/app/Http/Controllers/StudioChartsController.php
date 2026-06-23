<?php

namespace App\Http\Controllers;

use App\Models\Library\Chart;
use App\Models\Library\Song;
use App\Services\StudioChartAccessService;
use App\Support\StudioLibraryChartStorage;
use App\Support\StudioSongMetadata;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioChartsController extends Controller
{
    public function index(StudioChartAccessService $chartAccess, StudioSongMetadata $songMetadata): View
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->person_id !== null, 403);

        $person = $user->person()->with('instruments')->firstOrFail();
        $songs = $chartAccess->songsForPerson($person);
        $hasInstrumentAssignments = $person->instruments->isNotEmpty();

        $songMetadataById = $songs->mapWithKeys(
            fn (Song $song) => [$song->id => $songMetadata->forSong($song)],
        );

        return view('studio.charts.index', [
            'user' => $user,
            'person' => $person,
            'songs' => $songs,
            'songMetadataById' => $songMetadataById,
            'hasInstrumentAssignments' => $hasInstrumentAssignments,
        ]);
    }

    public function show(Song $song, StudioChartAccessService $chartAccess, StudioSongMetadata $songMetadata): View
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
            'songMetadata' => $songMetadata->forSong($song),
            'chartLinks' => $chartLinks,
            'hasInstrumentAssignments' => $person->instruments->isNotEmpty(),
        ]);
    }

    public function chartFile(
        Chart $chart,
        StudioChartAccessService $chartAccess,
        StudioLibraryChartStorage $chartStorage,
    ): Response|StreamedResponse {
        $user = auth()->user();
        abort_unless($user !== null && $user->person_id !== null, 403);

        $person = $user->person()->with('instruments')->firstOrFail();

        if (! $chartAccess->personCanAccessChart($person, $chart)) {
            abort(403);
        }

        $path = $chart->storage_reference;

        if ($path === null || ! $chartStorage->exists($path)) {
            Log::warning('studio.chart_file_missing', [
                'chart_id' => $chart->id,
                'storage_reference' => $path,
                'absolute_path' => $path !== null ? $chartStorage->absolutePath($path) : null,
                'readable' => $path !== null && is_readable($chartStorage->absolutePath($path)),
            ]);
            abort(404);
        }

        $filename = $chart->original_filename ?: basename($path);

        return $chartStorage->response(
            $path,
            $filename,
            $chart->mime_type ?: 'application/pdf',
        );
    }
}
