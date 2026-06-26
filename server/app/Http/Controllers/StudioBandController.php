<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBandProfileRequest;
use App\Services\BandProfileService;
use App\Support\BandStyles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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

    public function logo(BandProfileService $bandProfile): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->logo_path);
    }

    public function photo(BandProfileService $bandProfile): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->photo_path);
    }

    public function hero(BandProfileService $bandProfile): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->hero_photo_path);
    }

    public function press(BandProfileService $bandProfile): Response|StreamedResponse
    {
        return $this->assetResponse($bandProfile->portalBand()->press_photo_path);
    }

    private function assetResponse(?string $path): Response|StreamedResponse
    {
        if ($path === null || $path === '') {
            abort(404);
        }

        $disk = (string) config('portal.band_asset_disk', 'local');

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path);
    }
}
