<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\MusicalKey;
use App\Models\Song;
use App\Models\SongMood;
use App\Models\TimeSignature;
use App\Services\SongCodeAllocator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
        return view('songs.create', [
            'band' => $this->band(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate($this->songMetadataRules());

        $songCode = $this->songCodeAllocator->nextForBand($band);

        $song = Song::create([
            'band_id' => $band->id,
            'song_code' => $songCode,
            'name' => $validated['name'],
            'bpm' => $validated['bpm'] ?? null,
            'time_signature_id' => $validated['time_signature_id'] ?? null,
            'musical_key_id' => $validated['musical_key_id'] ?? null,
            'mood_id' => $validated['mood_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'director_notes' => $validated['director_notes'] ?? null,
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
            'timeSignature',
            'musicalKey',
            'mood',
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
            'song' => $song->load(['timeSignature', 'musicalKey', 'mood']),
            'timeSignatures' => TimeSignature::query()->where('active', true)->orderBy('sort_order')->get(),
            'musicalKeys' => MusicalKey::query()->where('active', true)->orderBy('sort_order')->get(),
            'moods' => SongMood::query()->where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Song $song): RedirectResponse
    {
        $this->ensureSongBelongsToBand($song);

        $validated = $request->validate(array_merge($this->songMetadataRules(), [
            'status' => ['nullable', 'string', 'in:draft,in_progress,ready,archived'],
        ]));

        $song->update($validated);

        return redirect()
            ->route('songs.show', $song)
            ->with('status', 'Song updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function songMetadataRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bpm' => ['nullable', 'integer', 'min:20', 'max:300'],
            'time_signature_id' => ['nullable', 'integer', Rule::exists('time_signatures', 'id')],
            'musical_key_id' => ['nullable', 'integer', Rule::exists('musical_keys', 'id')],
            'mood_id' => ['nullable', 'integer', Rule::exists('song_moods', 'id')],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'director_notes' => ['nullable', 'string'],
        ];
    }
}
