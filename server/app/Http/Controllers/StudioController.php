<?php

namespace App\Http\Controllers;

use App\Services\StudioBandInviteService;
use App\Services\StudioChartAccessService;
use App\Services\StudioMusicianResolverService;
use App\Services\StudioScheduleService;
use App\Services\StudioShowService;
use App\Services\StudioSongLibraryService;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(
        StudioChartAccessService $chartAccess,
        StudioBandInviteService $bandInvites,
        StudioShowService $shows,
        StudioScheduleService $schedule,
        StudioMusicianResolverService $musicians,
        StudioSongLibraryService $songLibrary,
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
            'hasMusicianLink' => $musician !== null,
            'musicLibrarySummary' => $isDirector ? $songLibrary->summaryForBand() : null,
        ]);
    }
}
