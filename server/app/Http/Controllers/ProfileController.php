<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\InstrumentReference;
use App\Services\PersonProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View|RedirectResponse
    {
        $user = auth()->user();

        if ($user === null || $user->person_id === null) {
            abort(403);
        }

        $person = $user->person()->with('instruments')->firstOrFail();
        $primary = $person->instruments->first(
            fn ($instrument) => (bool) $instrument->pivot->is_primary,
        );

        return view('studio.profile.edit', [
            'user' => $user,
            'person' => $person,
            'instruments' => InstrumentReference::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
            'primaryInstrumentSlug' => $primary?->slug ?? '',
            'additionalInstrumentSlugs' => $person->instruments
                ->filter(fn ($instrument) => ! $instrument->pivot->is_primary)
                ->pluck('slug')
                ->all(),
        ]);
    }

    public function update(UpdateProfileRequest $request, PersonProfileService $profileService): RedirectResponse
    {
        $person = $request->user()->person()->firstOrFail();

        $profileService->update($person, $request->validatedPayload());

        return redirect()
            ->route('studio')
            ->with('profile_updated', true);
    }
}
