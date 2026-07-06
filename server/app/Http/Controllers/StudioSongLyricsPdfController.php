<?php

namespace App\Http\Controllers;

use App\Models\Library\Song;
use App\Services\StudioSongLyricsPdfService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class StudioSongLyricsPdfController extends Controller
{
    public function download(Song $song, StudioSongLyricsPdfService $lyricsPdf): Response|RedirectResponse
    {
        abort_unless(auth()->user()?->isDirector(), 403);
        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);

        try {
            $result = $lyricsPdf->generateFromSavedLyrics($song->fresh());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('songs.edit', $song)
                ->with('lyrics_pdf_error', $exception->getMessage());
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('songs.edit', $song)
                ->with('lyrics_pdf_error', 'Unable to generate lyrics PDF right now.');
        }

        return response($result['contents'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
        ]);
    }
}
