<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Chart;
use App\Models\Song;
use App\Models\SongInstrumentPart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ChartController extends Controller
{
    use ResolvesBand;

    public function store(Request $request, Song $song): RedirectResponse
    {
        $this->ensureSongBelongsToBand($song);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'chart' => ['required', 'file', 'max:20480'],
            'notes' => ['nullable', 'string'],
        ]);

        $file = $validated['chart'];
        $directory = "charts/{$song->band_id}/{$song->song_code}";
        $path = $file->store($directory, 'local');
        $checksum = hash_file('sha256', Storage::disk('local')->path($path));

        Chart::create([
            'song_id' => $song->id,
            'title' => $validated['title'],
            'storage_reference' => $path,
            'checksum' => $checksum,
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('songs.show', $song)
            ->with('status', "Chart \"{$validated['title']}\" uploaded.");
    }

    public function assign(Request $request, Chart $chart): RedirectResponse
    {
        $song = $chart->song;
        abort_unless($song, 404);

        $this->ensureSongBelongsToBand($song);

        $validated = $request->validate([
            'song_instrument_part_ids' => ['required', 'array', 'min:1'],
            'song_instrument_part_ids.*' => ['integer', 'distinct', 'exists:song_instrument_parts,id'],
        ]);

        $parts = SongInstrumentPart::query()
            ->whereIn('id', $validated['song_instrument_part_ids'])
            ->where('song_id', $song->id)
            ->get();

        abort_unless($parts->count() === count($validated['song_instrument_part_ids']), 422);

        foreach ($parts as $part) {
            $part->update(['chart_id' => $chart->id]);
        }

        return redirect()
            ->route('songs.show', $song)
            ->with('status', "Chart \"{$chart->title}\" assigned to {$parts->count()} instrument part(s).");
    }
}
