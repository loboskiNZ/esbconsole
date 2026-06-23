<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\InstrumentReference;
use App\Services\PersonProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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

    public function photo(): Response|StreamedResponse
    {
        $user = auth()->user();

        if ($user === null || $user->person_id === null) {
            abort(403);
        }

        $person = $user->person()->firstOrFail();
        $path = $person->profilePhotoServePath();

        if ($path === null) {
            abort(404);
        }

        $disk = (string) config('portal.profile_photo_disk', 'local');

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path);
    }
}
