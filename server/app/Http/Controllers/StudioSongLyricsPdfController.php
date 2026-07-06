<?php

namespace App\Http\Controllers;

use App\Models\Library\Song;
use App\Services\StudioSongLyricsPdfService;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class StudioSongLyricsPdfController extends Controller
{
    public function generate(Song $song, StudioSongLyricsPdfService $lyricsPdf): RedirectResponse
    {
        $user = auth()->user();
        abort_unless($user !== null && $user->isDirector(), 403);
        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);

        try {
            $lyricsPdf->generateFromSavedLyrics($song->fresh(), $user);
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

        return redirect()
            ->route('songs.edit', $song)
            ->with('lyrics_pdf_generated', true);
    }
}
