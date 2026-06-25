<?php

namespace App\Http\Controllers;

use App\Services\StudioBandInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StudioBandInviteController extends Controller
{
    public function store(Request $request, StudioBandInviteService $bandInvites): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $bandInvites->createInvite(
            name: $validated['name'],
            days: (int) ($validated['days'] ?? 30),
        );

        return redirect()
            ->route('studio')
            ->with('invite_created', true);
    }
}
