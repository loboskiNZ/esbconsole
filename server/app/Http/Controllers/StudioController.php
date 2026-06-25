<?php

namespace App\Http\Controllers;

use App\Services\StudioBandInviteService;
use App\Services\StudioChartAccessService;
use Illuminate\View\View;

class StudioController extends Controller
{
    public function index(
        StudioChartAccessService $chartAccess,
        StudioBandInviteService $bandInvites,
    ): View {
        $user = auth()->user();
        $person = $user?->load(['person.instruments'])->person;

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
            'bandInvites' => $user?->isDirector()
                ? $bandInvites->invitesForDashboard()
                : collect(),
            'isDirector' => $user?->isDirector() ?? false,
        ]);
    }
}
