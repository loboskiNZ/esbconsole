<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudioShowRequest;
use App\Http\Requests\UpdateStudioShowRequest;
use App\Models\Show;
use App\Services\StudioPerformanceService;
use App\Services\StudioShowPlaylistService;
use App\Services\StudioShowService;
use App\Support\StudioLibraryAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudioShowsController extends Controller
{
    public function index(StudioShowService $shows): View
    {
        $user = auth()->user();

        return view('studio.shows.index', [
            'shows' => $shows->activeShowsForPortal(),
            'isDirector' => $user?->isDirector() ?? false,
        ]);
    }

    public function archived(StudioShowService $shows): View
    {
        return view('studio.shows.archived', [
            'shows' => $shows->archivedShowsForPortal(),
        ]);
    }

    public function create(): View
    {
        return view('studio.shows.create');
    }

    public function store(StoreStudioShowRequest $request, StudioShowService $shows): RedirectResponse
    {
        $show = $shows->createShow($request->validatedPayload());

        return redirect()
            ->route('studio.shows.show', $show)
            ->with('show_created', true);
    }

    public function show(
        Show $show,
        StudioShowService $shows,
        StudioPerformanceService $performances,
        StudioShowPlaylistService $playlist,
        StudioLibraryAvailability $library,
    ): View {
        $user = auth()->user();
        $portalShow = $shows->showForPortal($show->id);
        $playlistView = $playlist->playlistViewForShow($portalShow->id);

        return view('studio.shows.show', [
            'show' => $portalShow,
            'performances' => $performances->performancesForShow($portalShow->id),
            'playlistEntries' => $playlistView['entries'],
            'playlistSummary' => $playlistView['summary'],
            'showInstrumentParts' => $playlistView['show_instrument_parts'],
            'selectableSongs' => $user?->isDirector() ? $playlist->selectableSongsForShow($portalShow->id) : collect(),
            'libraryAvailable' => $library->isAvailable(),
            'isDirector' => $user?->isDirector() ?? false,
        ]);
    }

    public function edit(Show $show, StudioShowService $shows): View
    {
        return view('studio.shows.edit', [
            'show' => $shows->showForPortal($show->id),
        ]);
    }

    public function update(UpdateStudioShowRequest $request, Show $show, StudioShowService $shows): RedirectResponse
    {
        $updated = $shows->updateShow($show, $request->validatedPayload());

        return redirect()
            ->route('studio.shows.show', $updated)
            ->with('show_updated', true);
    }

    public function archive(Show $show, StudioShowService $shows): RedirectResponse
    {
        $shows->archiveShow($show);

        return redirect()
            ->route('studio.shows.index')
            ->with('show_archived', $show->name);
    }

    public function restore(Show $show, StudioShowService $shows): RedirectResponse
    {
        $restored = $shows->restoreShow($show);

        return redirect()
            ->route('studio.shows.index')
            ->with('show_restored', $restored->name);
    }
}
