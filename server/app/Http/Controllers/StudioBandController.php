<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBandProfileRequest;
use App\Services\BandProfileService;
use App\Support\BandStyles;
use App\Support\CloudStudioMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioBandController extends Controller
{
    public function edit(BandProfileService $bandProfile): View
    {
        $band = $bandProfile->portalBand();

        return view('studio.band.edit', [
            'band' => $band,
            'stylesInput' => BandStyles::toInputValue($band->styles),
        ]);
    }

    public function update(UpdateBandProfileRequest $request, BandProfileService $bandProfile): RedirectResponse
    {
        $band = $bandProfile->portalBand();

        $bandProfile->update(
            band: $band,
            payload: $request->validatedPayload(),
            logo: $request->file('logo'),
            photo: $request->file('photo'),
            heroPhoto: $request->file('hero_photo'),
            pressPhoto: $request->file('press_photo'),
        );

        return redirect()
            ->route('studio.band.edit')
            ->with('band_updated', true);
    }

    public function logo(BandProfileService $bandProfile, CloudStudioMediaStorage $mediaStorage): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->logo_path, $mediaStorage);
    }

    public function photo(BandProfileService $bandProfile, CloudStudioMediaStorage $mediaStorage): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->photo_path, $mediaStorage);
    }

    public function hero(BandProfileService $bandProfile, CloudStudioMediaStorage $mediaStorage): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->hero_photo_path, $mediaStorage);
    }

    public function press(BandProfileService $bandProfile, CloudStudioMediaStorage $mediaStorage): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->press_photo_path, $mediaStorage);
    }

    private function assetResponse(?string $path, CloudStudioMediaStorage $mediaStorage): Response|StreamedResponse
    {
        if ($path === null || $path === '') {
            abort(404);
        }

        if (! $mediaStorage->exists($path)) {
            abort(404);
        }

        return $mediaStorage->response($path, basename($path));
    }
}
