<?php

namespace App\Http\Controllers;

use App\Models\Library\MusicalKey;
use App\Models\Library\Song;
use App\Models\Library\SongMood;
use App\Models\Library\TimeSignature;
use App\Services\StudioSongInstrumentPartService;
use App\Services\StudioSongLibraryService;
use App\Support\SafeInternalRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudioSongsController extends Controller
{
    public function index(Request $request, StudioSongLibraryService $library): View
    {
        $showArchived = $request->boolean('archived');
        $query = $request->string('q')->toString();
        $genre = $request->string('genre')->toString();
        $songs = $library->songsForLibrary(
            showArchived: $showArchived,
            query: $query !== '' ? $query : null,
            genre: $genre !== '' ? $genre : null,
        );

        return view('studio.songs.index', [
            'songs' => $songs,
            'summary' => $library->summaryForBand(),
            'genreOptions' => $library->genreOptionsForBand(),
            'showUsageBySongId' => $library->showNamesForSongs($songs->pluck('id')->all()),
            'showArchived' => $showArchived,
            'query' => $query,
            'genre' => $genre,
            'libraryReturnTo' => app(SafeInternalRedirect::class)->songLibraryReturnPath(),
        ]);
    }

    public function create(StudioSongLibraryService $library): View
    {
        return view('studio.songs.create', [
            'musicalKeys' => MusicalKey::query()->where('active', true)->orderBy('sort_order')->get(),
            'hasDurationField' => $library->hasDurationColumn(),
        ]);
    }

    public function store(Request $request, StudioSongLibraryService $library): RedirectResponse
    {
        $validated = $request->validate($this->createSongRules($library));

        $durationSeconds = null;
        if ($library->hasDurationColumn() && isset($validated['duration'])) {
            $durationSeconds = $library->parseDurationInput($validated['duration']);
        }
        unset($validated['duration']);

        $song = $library->createSong([
            ...$validated,
            'duration_seconds' => $durationSeconds,
        ]);

        return redirect()
            ->route('songs.edit', $song)
            ->with('song_created', true);
    }

    public function archive(Song $song, StudioSongLibraryService $library): RedirectResponse
    {
        $this->ensureSongBelongsToPortalBand($song);
        $library->archiveSong($song);

        return redirect()
            ->route('songs.index', request()->only(['q', 'genre', 'archived']))
            ->with('song_archived', $song->name);
    }

    public function restore(Song $song, StudioSongLibraryService $library): RedirectResponse
    {
        $this->ensureSongBelongsToPortalBand($song);
        $library->restoreSong($song);

        return redirect()
            ->route('songs.index', ['archived' => 1])
            ->with('song_restored', $song->fresh()->name);
    }

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
    private function createSongRules(StudioSongLibraryService $library): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'bpm' => ['nullable', 'integer', 'min:20', 'max:300'],
            'musical_key_id' => ['nullable', 'integer', Rule::exists(MusicalKey::class, 'id')],
            'director_notes' => ['nullable', 'string'],
            'spotify_url' => ['nullable', 'string', 'max:2048', 'url'],
            'youtube_url' => ['nullable', 'string', 'max:2048', 'url'],
        ];

        if ($library->hasDurationColumn()) {
            $rules['duration'] = ['nullable', 'string', 'max:16'];
        }

        return $rules;
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
