<?php

namespace App\Http\Controllers;

use App\Models\Library\MusicalKey;
use App\Models\Library\Song;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Services\StudioSongInstrumentPartService;
use App\Support\SafeInternalRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudioSongsController extends Controller
{
    public function edit(Request $request, Song $song, StudioSongInstrumentPartService $instrumentParts): View
    {
        $this->ensureSongBelongsToPortalBand($song);

        $song->load(['timeSignature', 'musicalKey', 'mood', 'assets']);

        return view('studio.songs.edit', [
            'song' => $song,
            'timeSignatures' => TimeSignature::query()->where('active', true)->orderBy('sort_order')->get(),
            'musicalKeys' => MusicalKey::query()->where('active', true)->orderBy('sort_order')->get(),
            'moods' => SongMood::query()->where('active', true)->orderBy('sort_order')->get(),
            'returnTo' => $request->query('return_to'),
            'songInstrumentParts' => $instrumentParts->partsForSongEdit($song),
            'attachableInstrumentParts' => $instrumentParts->attachablePartsForSong($song),
            'songAssetTypes' => \App\Support\SongAssetType::labels(),
            'songAssetMaxMb' => (int) ceil(((int) config('portal.song_asset_max_kb', 153600)) / 1024),
        ]);
    }

    public function update(Request $request, Song $song, SafeInternalRedirect $redirects): RedirectResponse
    {
        $this->ensureSongBelongsToPortalBand($song);

        $validated = $request->validate($this->songMetadataRules());
        $returnTo = $validated['return_to'] ?? null;
        unset($validated['return_to']);

        $song->update($validated);

        $fallback = route('studio.charts.show', $song, absolute: false);

        return redirect()
            ->to($redirects->resolve($returnTo, $fallback))
            ->with('song_updated', true);
    }

    private function ensureSongBelongsToPortalBand(Song $song): void
    {
        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function songMetadataRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'bpm' => ['nullable', 'integer', 'min:20', 'max:300'],
            'time_signature_id' => ['nullable', 'integer', Rule::exists(TimeSignature::class, 'id')],
            'musical_key_id' => ['nullable', 'integer', Rule::exists(MusicalKey::class, 'id')],
            'mood_id' => ['nullable', 'integer', Rule::exists(SongMood::class, 'id')],
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
            'spotify_url' => ['nullable', 'string', 'max:2048', 'url'],
            'youtube_url' => ['nullable', 'string', 'max:2048', 'url'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
