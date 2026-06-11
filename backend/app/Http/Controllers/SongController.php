<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\Song;
use App\Services\SongCodeAllocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SongController extends Controller
{
    use ResolvesBand;

    public function __construct(
        private readonly SongCodeAllocator $songCodeAllocator,
    ) {}

    public function index(): View
    {
        $band = $this->band();
        $songs = $band->songs()->orderBy('song_code')->get();

        return view('songs.index', [
            'band' => $band,
            'songs' => $songs,
        ]);
    }

    public function create(): View
    {
        $band = $this->band();

        return view('songs.create', [
            'band' => $band,
            'suggestedSongCode' => $this->songCodeAllocator->nextForBand($band),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'song_code' => ['nullable', 'string', 'size:3', 'regex:/^\d{3}$/'],
            'bpm' => ['nullable', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $songCode = $validated['song_code'] ?? $this->songCodeAllocator->nextForBand($band);

        abort_if(
            $band->songs()->where('song_code', $songCode)->exists(),
            422,
            'Song code already in use.',
        );

        $song = Song::create([
            'band_id' => $band->id,
            'song_code' => $songCode,
            'name' => $validated['name'],
            'bpm' => $validated['bpm'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => Song::STATUS_DRAFT,
        ]);

        return redirect()
            ->route('songs.show', $song)
            ->with('status', "Song \"{$song->name}\" ({$song->song_code}) created.");
    }

    public function show(Song $song): View
    {
        $this->ensureSongBelongsToBand($song);

        $band = $this->band();

        $song->load([
            'cues' => fn ($query) => $query->inPerformanceOrder(),
            'songInstrumentParts.instrumentPart',
            'songInstrumentParts.chart',
            'charts',
        ]);

        $availableInstrumentParts = $band->instrumentParts()
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->reject(fn ($part) => $song->songInstrumentParts->contains('instrument_part_id', $part->id));

        return view('songs.show', [
            'band' => $band,
            'song' => $song,
            'availableInstrumentParts' => $availableInstrumentParts,
        ]);
    }

    public function edit(Song $song): View
    {
        $this->ensureSongBelongsToBand($song);

        return view('songs.edit', [
            'band' => $this->band(),
            'song' => $song,
        ]);
    }

    public function update(Request $request, Song $song): RedirectResponse
    {
        $this->ensureSongBelongsToBand($song);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'bpm' => ['nullable', 'integer', 'min:1', 'max:999'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:draft,in_progress,ready,archived'],
        ]);

        $song->update($validated);

        return redirect()
            ->route('songs.show', $song)
            ->with('status', 'Song updated.');
    }
}
