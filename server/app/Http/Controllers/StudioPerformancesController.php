<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudioPerformanceRequest;
use App\Http\Requests\UpdateStudioPerformanceRequest;
use App\Models\Performance;
use App\Services\StudioPerformanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StudioPerformancesController extends Controller
{
    public function index(StudioPerformanceService $performances): View
    {
        return view('studio.performances.index', [
            'performances' => $performances->performancesForPortal(),
            'isDirector' => auth()->user()?->isDirector() ?? false,
        ]);
    }

    public function create(StudioPerformanceService $performances): View
    {
        return view('studio.performances.create', [
            'shows' => $performances->selectableShowsForPortal(),
        ]);
    }

    public function store(StoreStudioPerformanceRequest $request, StudioPerformanceService $performances): RedirectResponse
    {
        $performance = $performances->createPerformance($request->validatedPayload());

        return redirect()
            ->route('studio.performances.show', $performance)
            ->with('performance_created', true);
    }

    public function show(Performance $performance, StudioPerformanceService $performances): View
    {
        return view('studio.performances.show', [
            'performance' => $performances->performanceForPortal($performance->id),
            'availabilityAssignments' => $performances->availabilityAssignmentsForPerformance($performance->id),
            'isDirector' => auth()->user()?->isDirector() ?? false,
        ]);
    }

    public function edit(Performance $performance, StudioPerformanceService $performances): View
    {
        return view('studio.performances.edit', [
            'performance' => $performances->performanceForPortal($performance->id),
            'shows' => $performances->selectableShowsForPortal(),
        ]);
    }

    public function update(
        UpdateStudioPerformanceRequest $request,
        Performance $performance,
        StudioPerformanceService $performances,
    ): RedirectResponse {
        $updated = $performances->updatePerformance($performance, $request->validatedPayload());

        return redirect()
            ->route('studio.performances.show', $updated)
            ->with('performance_updated', true);
    }
}
