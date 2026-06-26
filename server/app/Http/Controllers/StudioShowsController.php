<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudioShowRequest;
use App\Models\Show;
use App\Services\StudioShowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudioShowsController extends Controller
{
    public function index(StudioShowService $shows): View
    {
        return view('studio.shows.index', [
            'shows' => $shows->showsForPortal(),
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

    public function show(Show $show, StudioShowService $shows): View
    {
        $portalShow = $shows->showForPortal($show->id);

        return view('studio.shows.show', [
            'show' => $portalShow,
        ]);
    }
}
