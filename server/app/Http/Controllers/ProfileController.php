<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\InstrumentReference;
use App\Services\PersonProfileService;
use App\Support\CloudStudioMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $instruments = InstrumentReference::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('studio.profile.edit', [
            'user' => $user,
            'person' => $person,
            'instruments' => $instruments,
            'instrumentOptions' => $instruments
                ->map(fn (InstrumentReference $instrument) => [
                    'id' => $instrument->slug,
                    'name' => $instrument->name,
                ])
                ->values()
                ->all(),
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

        $profileService->update(
            $person,
            $request->validatedPayload(),
            $request->file('profile_photo'),
        );

        return redirect()
            ->route('studio')
            ->with('profile_updated', true);
    }

    public function photo(CloudStudioMediaStorage $mediaStorage): Response|StreamedResponse
    {
        $user = auth()->user();

        if ($user === null || $user->person_id === null) {
            abort(403);
        }

        $person = $user->person()->firstOrFail();
        $path = $person->profilePhotoServePath();

        if ($path === null || ! $mediaStorage->exists($path)) {
            abort(404);
        }

        return $mediaStorage->response($path, basename($path));
    }
}
