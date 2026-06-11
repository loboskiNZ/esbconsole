<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\InstrumentPart;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SongInstrumentPartController extends Controller
{
    use ResolvesBand;

    public function store(Request $request, Song $song): RedirectResponse
    {
        $this->ensureSongBelongsToBand($song);

        $validated = $request->validate([
            'instrument_part_id' => ['required', 'integer', 'exists:instrument_parts,id'],
            'notes' => ['nullable', 'string'],
        ]);

        $instrumentPart = InstrumentPart::findOrFail($validated['instrument_part_id']);
        $this->ensureBandOwns($instrumentPart);

        abort_if(
            $song->songInstrumentParts()->where('instrument_part_id', $instrumentPart->id)->exists(),
            422,
            'Instrument part already assigned to this song.',
        );

        SongInstrumentPart::create([
            'song_id' => $song->id,
            'instrument_part_id' => $instrumentPart->id,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('songs.show', $song)
            ->with('status', "\"{$instrumentPart->name}\" assigned to song.");
    }

    public function destroy(Song $song, SongInstrumentPart $songInstrumentPart): RedirectResponse
    {
        $this->ensureSongBelongsToBand($song);
        abort_unless($songInstrumentPart->song_id === $song->id, 404);

        $partName = $songInstrumentPart->instrumentPart->name;
        $songInstrumentPart->delete();

        return redirect()
            ->route('songs.show', $song)
            ->with('status', "\"{$partName}\" removed from song.");
    }
}
