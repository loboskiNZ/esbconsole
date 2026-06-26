<?php

namespace App\Http\Controllers;

use App\Exceptions\StudioMusicianNotLinkedException;
use App\Http\Requests\StorePerformanceRsvpRequest;
use App\Models\Performance;
use App\Services\PerformanceIcsExportService;
use App\Services\StudioMusicianResolverService;
use App\Services\StudioPerformanceRsvpService;
use App\Services\StudioPerformanceService;
use App\Services\StudioScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StudioCalendarController extends Controller
{
    public function index(
        StudioScheduleService $schedule,
        StudioMusicianResolverService $musicians,
    ): View {
        $user = auth()->user();
        $musician = $musicians->musicianForUser($user);
        $performances = $schedule->performancesForCalendar();
        $scheduleItems = $schedule->buildScheduleItems($performances, $musician);

        return view('studio.calendar.index', [
            'scheduleItems' => $scheduleItems,
            'upcomingItems' => $schedule->buildScheduleItems(
                $schedule->upcomingPerformancesForPortal(),
                $musician,
            ),
            'hasMusicianLink' => $musician !== null,
        ]);
    }
}
