<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Cue;
use App\Models\Song;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CueController extends Controller
{
    use ResolvesBand;

    public function store(Request $request, Song $song): RedirectResponse
    {
        $this->ensureSongBelongsToBand($song);

        $validated = $request->validate([
            'cue_number' => ['required', 'string', 'size:3', 'regex:/^\d{3}$/'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        abort_if(
            $song->cues()->where('cue_number', $validated['cue_number'])->exists(),
            422,
            'Cue number already exists for this song.',
        );

        $nextSequence = ((int) $song->cues()->max('sequence_order')) + 1;

        Cue::create([
            'song_id' => $song->id,
            'cue_number' => $validated['cue_number'],
            'sequence_order' => $nextSequence,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('songs.show', $song)
            ->with('status', "Cue {$validated['cue_number']} created.");
    }

    public function destroy(Song $song, Cue $cue): RedirectResponse
    {
        $this->ensureSongBelongsToBand($song);
        abort_unless($cue->song_id === $song->id, 404);

        $cueNumber = $cue->cue_number;
        $cue->delete();

        return redirect()
            ->route('songs.show', $song)
            ->with('status', "Cue {$cueNumber} removed.");
    }
}
