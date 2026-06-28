<?php

namespace App\Http\Controllers;

use App\Models\Library\InstrumentPart;
use App\Models\Library\Song;
use App\Models\Library\SongInstrumentPart;
use App\Services\StudioSongInstrumentPartService;
use App\Support\SafeInternalRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class StudioSongInstrumentPartController extends Controller
{
    public function store(
        Request $request,
        Song $song,
        StudioSongInstrumentPartService $parts,
        SafeInternalRedirect $redirects,
    ): RedirectResponse {
        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);

        $validated = $request->validate([
            'instrument_part_id' => ['nullable', 'integer', Rule::exists(InstrumentPart::class, 'id')],
            'new_part_name' => ['nullable', 'string', 'max:255'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        if (
            ($validated['instrument_part_id'] ?? null) === null
            && trim((string) ($validated['new_part_name'] ?? '')) === ''
        ) {
            return redirect()
                ->to($this->editUrl($song, $validated['return_to'] ?? null, $redirects))
                ->withErrors(['instrument_part' => 'Choose an existing instrument part or enter a new part name.'])
                ->withInput();
        }

        if (
            ($validated['instrument_part_id'] ?? null) !== null
            && trim((string) ($validated['new_part_name'] ?? '')) !== ''
        ) {
            return redirect()
                ->to($this->editUrl($song, $validated['return_to'] ?? null, $redirects))
                ->withErrors(['instrument_part' => 'Choose either an existing part or a new part name, not both.'])
                ->withInput();
        }

        try {
            if (($validated['instrument_part_id'] ?? null) !== null) {
                $parts->attachExistingPart($song, (int) $validated['instrument_part_id']);
            } else {
                $parts->createAndAttachPart($song, (string) $validated['new_part_name']);
            }
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->to($this->editUrl($song, $validated['return_to'] ?? null, $redirects))
                ->withErrors(['instrument_part' => $exception->getMessage()])
                ->withInput();
        }

        return redirect()
            ->to($this->editUrl($song, $validated['return_to'] ?? null, $redirects))
            ->with('song_part_added', true);
    }

    public function destroy(
        Request $request,
        Song $song,
        SongInstrumentPart $songInstrumentPart,
        StudioSongInstrumentPartService $parts,
        SafeInternalRedirect $redirects,
    ): RedirectResponse {
        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);

        $validated = $request->validate([
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $hadChart = $parts->detachFromSong($song, $songInstrumentPart);

        return redirect()
            ->to($this->editUrl($song, $validated['return_to'] ?? null, $redirects))
            ->with('song_part_removed', $hadChart ? 'chart_preserved' : true);
    }

    private function editUrl(Song $song, ?string $returnTo, SafeInternalRedirect $redirects): string
    {
        $params = ['song' => $song];

        if ($returnTo !== null && trim($returnTo) !== '') {
            $params['return_to'] = $redirects->resolve(
                $returnTo,
                route('studio.charts.show', $song, absolute: false),
            );
        }

        return route('songs.edit', $params, absolute: false);
    }
}
