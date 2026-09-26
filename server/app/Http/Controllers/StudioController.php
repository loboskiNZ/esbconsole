<?php

namespace App\Http\Controllers;

use App\Models\Performance;
use App\Services\PerformanceCapacityService;
use App\Services\StudioBandInviteService;
use App\Services\StudioChartAccessService;
use App\Services\StudioMusicianResolverService;
use App\Services\StudioScheduleService;
use App\Services\StudioShowService;
use App\Services\StudioSongLibraryService;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class StudioController extends Controller
{
    /**
     * Door / Check-in stays off the home card until the performance is this close.
     */
    private const DOOR_HOME_WINDOW_DAYS = 7;

    public function index(
        StudioChartAccessService $chartAccess,
        StudioBandInviteService $bandInvites,
        StudioShowService $shows,
        StudioScheduleService $schedule,
        StudioMusicianResolverService $musicians,
        StudioSongLibraryService $songLibrary,
        PerformanceCapacityService $capacity,
    ): View {
        $user = auth()->user();
        $person = $user?->load(['person.instruments'])->person;
        $isDirector = $user?->isDirector() ?? false;
        $musician = $user ? $musicians->musicianForUser($user) : null;

        $songCount = 0;
        $chartCount = 0;

        if ($person !== null) {
            $songCount = $chartAccess->songCountForPerson($person);
            $chartCount = $chartAccess->chartCountForPerson($person);
        }

        $upcomingPerformances = $schedule->upcomingPerformancesForPortal(limit: 5);

        return view('studio.index', [
            'user' => $user,
            'person' => $person,
            'songCount' => $songCount,
            'chartCount' => $chartCount,
            'bandInvites' => $isDirector ? $bandInvites->shareableInvitesForDashboard() : collect(),
            'legacyUnusableInviteCount' => $isDirector ? $bandInvites->legacyUnusableCount() : 0,
            'isDirector' => $isDirector,
            'shows' => $shows->activeShowsForPortal(limit: 5),
            'scheduleItems' => $schedule->buildScheduleItems($upcomingPerformances, $musician),
            'ticketingHome' => $isDirector ? $this->ticketingHome($upcomingPerformances, $capacity) : [],
            'hasMusicianLink' => $musician !== null,
            'musicLibrarySummary' => $isDirector ? $songLibrary->summaryForBand() : null,
        ]);
    }

    /**
     * Director home summaries. Counts come from PerformanceCapacityService.
     *
     * @param  Collection<int, Performance>  $performances
     * @return array<int, array{enabled: bool, setup: bool, door: bool, allocated: int, remaining: ?int, checked_in: int}>
     */
    private function ticketingHome(Collection $performances, PerformanceCapacityService $capacity): array
    {
        $performances->load('ticketingConfiguration');
        $cards = [];

        foreach ($performances as $performance) {
            $snapshot = $capacity->snapshot($performance);
            $enabled = $snapshot['enabled'];

            $cards[$performance->id] = [
                'enabled' => $enabled,
                'setup' => ! $enabled && $performance->performance_type === Performance::TYPE_LIVE,
                'door' => $enabled && $this->doorIsImminent($performance),
                'allocated' => $snapshot['seats_held'],
                'remaining' => $snapshot['remaining'],
                'checked_in' => $snapshot['checked_in'],
            ];
        }

        return $cards;
    }

    private function doorIsImminent(Performance $performance): bool
    {
        $date = $performance->performance_date;
        if ($date === null) {
            return false;
        }

        $today = now()->startOfDay();
        $day = $date->copy()->startOfDay();

        return $day->greaterThanOrEqualTo($today)
            && $day->lessThanOrEqualTo($today->copy()->addDays(self::DOOR_HOME_WINDOW_DAYS));
    }
}
