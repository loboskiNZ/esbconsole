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
            'mood_intention' => $validated['mood_intention'] ?? null,
            'performance_feel' => $validated['performance_feel'] ?? null,
            'arrangement_comments' => $validated['arrangement_comments'] ?? null,
            'genre' => $validated['genre'] ?? null,
            'style' => $validated['style'] ?? null,
            'tempo_feel' => $validated['tempo_feel'] ?? null,
            'count_in' => $validated['count_in'] ?? null,
            'reference_url' => $validated['reference_url'] ?? null,
            'reference_title' => $validated['reference_title'] ?? null,
            'reference_notes' => $validated['reference_notes'] ?? null,
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

        $missingChartCount = $song->songInstrumentParts
            ->filter(fn ($sip) => $sip->chart_id === null)
            ->count();

        return view('songs.show', [
            'band' => $band,
            'song' => $song,
            'availableInstrumentParts' => $availableInstrumentParts,
            'chartCount' => $song->charts->count(),
            'instrumentPartCount' => $song->songInstrumentParts->count(),
            'missingChartCount' => $missingChartCount,
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
            'genre' => ['nullable', 'string', 'max:100'],
            'style' => ['nullable', 'string', 'max:100'],
            'tempo_feel' => ['nullable', 'string', 'max:100'],
            'count_in' => ['nullable', 'integer', 'min:0', 'max:16'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'director_notes' => ['nullable', 'string'],
            'mood_intention' => ['nullable', 'string'],
            'performance_feel' => ['nullable', 'string'],
            'arrangement_comments' => ['nullable', 'string'],
            'reference_url' => ['nullable', 'string', 'max:2048', 'url'],
            'reference_title' => ['nullable', 'string', 'max:255'],
            'reference_notes' => ['nullable', 'string'],
        ];
    }
}
