<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesBand;
use App\Models\AbletonShowFile;
use App\Models\Show;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ShowController extends Controller
{
    use ResolvesBand;

    public function index(): View
    {
        $band = $this->band();
        $shows = $band->shows()->withCount('playlistItems')->orderBy('name')->get();
        $activeShowId = session('active_show_id');

        return view('shows.index', [
            'band' => $band,
            'shows' => $shows,
            'activeShowId' => $activeShowId,
        ]);
    }

    public function create(): View
    {
        return view('shows.create', [
            'band' => $this->band(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $band = $this->band();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $slug = Str::slug($validated['name']);

        $abletonFile = AbletonShowFile::create([
            'band_id' => $band->id,
            'name' => "{$validated['name']} — Ableton Show File",
            'storage_reference' => "pending/ableton/{$slug}.als",
            'checksum' => hash('sha256', 'pending:'.$validated['name'].':'.now()->timestamp),
            'notes' => 'Placeholder pending Ableton show file attachment.',
        ]);

        $show = Show::create([
            'band_id' => $band->id,
            'ableton_show_file_id' => $abletonFile->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'lifecycle_state' => 'draft',
        ]);

        session(['active_show_id' => $show->id]);

        return redirect()
            ->route('shows.show', $show)
            ->with('status', "Show \"{$show->name}\" created.");
    }

    public function show(Show $show): View
    {
        $this->ensureShowBelongsToBand($show);

        session(['active_show_id' => $show->id]);

        $show->load(['playlistItems.song.cues', 'playlistItems.song.songInstrumentParts.instrumentPart']);

        return view('shows.show', [
            'band' => $this->band(),
            'show' => $show,
        ]);
    }

    public function edit(Show $show): View
    {
        $this->ensureShowBelongsToBand($show);

        return view('shows.edit', [
            'band' => $this->band(),
            'show' => $show,
        ]);
    }

    public function update(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $show->update($validated);

        return redirect()
            ->route('shows.show', $show)
            ->with('status', 'Show updated.');
    }

    public function activate(Request $request, Show $show): RedirectResponse
    {
        $this->ensureShowBelongsToBand($show);

        session(['active_show_id' => $show->id]);

        return redirect()->route('shows.show', $show);
    }
}
