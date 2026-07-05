<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSongAssetRequest;
use App\Models\Library\Song;
use App\Models\Library\SongAsset;
use App\Services\SongAssetStorageService;
use App\Support\CloudStudioMediaStorage;
use App\Support\SafeInternalRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioSongAssetController extends Controller
{
    public function store(
        StoreSongAssetRequest $request,
        Song $song,
        SongAssetStorageService $assetStorage,
        SafeInternalRedirect $redirects,
    ): RedirectResponse {
        $this->ensureSongBelongsToPortalBand($song);

        $validated = $request->validated();
        $assetType = $assetStorage->resolveAssetType(
            $validated['asset_type'],
            $request->file('file'),
        );

        $assetStorage->store(
            $song,
            $request->file('file'),
            $assetType,
            trim((string) ($validated['label'] ?? '')),
            trim((string) ($validated['notes'] ?? '')),
            $request->user(),
        );

        $returnTo = $validated['return_to'] ?? null;
        $fallback = route('songs.edit', array_filter([
            'song' => $song,
            'return_to' => $returnTo,
        ]), absolute: false);

        return redirect()
            ->to($redirects->resolve($returnTo, $fallback))
            ->with('song_asset_uploaded', true);
    }

    public function file(
        Song $song,
        SongAsset $songAsset,
        CloudStudioMediaStorage $mediaStorage,
    ): Response|StreamedResponse {
        $this->ensureSongBelongsToPortalBand($song);
        abort_unless((int) $songAsset->song_id === (int) $song->id, 404);

        $path = $songAsset->storage_reference;

        if ($path === null || ! $mediaStorage->exists($path)) {
            abort(404);
        }

        return $mediaStorage->response(
            $path,
            $songAsset->original_filename,
            $songAsset->mime_type,
        );
    }

    public function destroy(
        Request $request,
        Song $song,
        SongAsset $songAsset,
        SongAssetStorageService $assetStorage,
        SafeInternalRedirect $redirects,
    ): RedirectResponse {
        $this->ensureSongBelongsToPortalBand($song);
        abort_unless((int) $songAsset->song_id === (int) $song->id, 404);

        $validated = $request->validate([
            'return_to' => ['nullable', 'string', 'max:2048'],
        ]);

        $label = $songAsset->displayName();

        try {
            $assetStorage->destroy($songAsset);
        } catch (\Throwable) {
            return redirect()
                ->to($this->editUrl($song, $validated['return_to'] ?? null, $redirects))
                ->withErrors(['song_asset' => 'Unable to delete song file. Please try again.']);
        }

        return redirect()
            ->to($this->editUrl($song, $validated['return_to'] ?? null, $redirects))
            ->with('song_asset_deleted', $label);
    }

    private function editUrl(Song $song, ?string $returnTo, SafeInternalRedirect $redirects): string
    {
        $params = ['song' => $song];

        if ($returnTo !== null && trim($returnTo) !== '') {
            $params['return_to'] = $redirects->resolve(
                $returnTo,
                route('studio.charts.show', $song, absolute: false),
            );
        }

        return route('songs.edit', $params, absolute: false);
    }

    private function ensureSongBelongsToPortalBand(Song $song): void
    {
        abort_unless($song->band_id === (int) config('portal.band_id', 1), 404);
    }
}
