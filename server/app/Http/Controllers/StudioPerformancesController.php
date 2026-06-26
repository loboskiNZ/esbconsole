<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePerformanceRsvpRequest;
use App\Http\Requests\StoreStudioPerformanceRequest;
use App\Http\Requests\UpdateStudioPerformanceRequest;
use App\Exceptions\StudioMusicianNotLinkedException;
use App\Models\Performance;
use App\Services\PerformanceIcsExportService;
use App\Services\StudioMusicianResolverService;
use App\Services\StudioPerformanceRsvpService;
use App\Services\StudioPerformanceService;
use App\Services\StudioScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
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

    public function show(
        Performance $performance,
        StudioPerformanceService $performances,
        StudioMusicianResolverService $musicians,
        StudioPerformanceRsvpService $rsvp,
        StudioScheduleService $schedule,
    ): View {
        $user = auth()->user();
        $musician = $musicians->musicianForUser($user);
        $portalPerformance = $performances->performanceForPortal($performance->id);
        $assignment = $rsvp->assignmentForMusician($portalPerformance, $musician);

        return view('studio.performances.show', [
            'performance' => $portalPerformance,
            'availabilityAssignments' => $performances->availabilityAssignmentsForPerformance($performance->id),
            'isDirector' => $user?->isDirector() ?? false,
            'hasMusicianLink' => $musician !== null,
            'rsvpAssignment' => $assignment,
            'rsvpLabel' => $rsvp->rsvpLabelForAssignment($assignment),
            'scheduleCard' => $schedule->serializePerformanceCard($portalPerformance, $assignment),
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

    public function rsvp(
        StorePerformanceRsvpRequest $request,
        Performance $performance,
        StudioPerformanceRsvpService $rsvp,
    ): RedirectResponse {
        try {
            $rsvp->submitRsvp(auth()->user(), $performance, $request->validatedPayload());
        } catch (StudioMusicianNotLinkedException $exception) {
            return back()->with('rsvp_error', $exception->getMessage());
        }

        return back()->with('rsvp_saved', true);
    }

    public function calendar(
        Performance $performance,
        StudioPerformanceService $performances,
        PerformanceIcsExportService $ics,
    ): Response {
        $portalPerformance = $performances->performanceForPortal($performance->id);
        $body = $ics->build($portalPerformance);

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="performance-'.$portalPerformance->public_id.'.ics"',
        ]);
    }
}
