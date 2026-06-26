<?php

namespace App\Http\Controllers;

use App\Services\StudioBandInviteService;
use App\Services\StudioChartAccessService;
use App\Services\StudioShowService;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(
        StudioChartAccessService $chartAccess,
        StudioBandInviteService $bandInvites,
        StudioShowService $shows,
    ): View {
        $user = auth()->user();
        $person = $user?->load(['person.instruments'])->person;
        $isDirector = $user?->isDirector() ?? false;

        $songCount = 0;
        $chartCount = 0;

        if ($person !== null) {
            $songCount = $chartAccess->songCountForPerson($person);
            $chartCount = $chartAccess->chartCountForPerson($person);
        }

        return view('studio.index', [
            'user' => $user,
            'person' => $person,
            'songCount' => $songCount,
            'chartCount' => $chartCount,
            'bandInvites' => $isDirector ? $bandInvites->shareableInvitesForDashboard() : collect(),
            'legacyUnusableInviteCount' => $isDirector ? $bandInvites->legacyUnusableCount() : 0,
            'isDirector' => $isDirector,
            'shows' => $shows->showsForPortal(limit: 5),
        ]);
    }
}
