<?php

namespace App\Services;

use App\Models\Band;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class BandAssetStorageService
{
    public function disk(): string
    {
        return (string) config('portal.band_asset_disk', 'local');
    }

    public function storagePrefix(Band $band): string
    {
        return 'portal/band-assets/'.$band->id;
    }

    public function storeLogo(Band $band, UploadedFile $file): string
    {
        return $this->storeAsset($band, $file, 'logo');
    }

    public function storePhoto(Band $band, UploadedFile $file): string
    {
        return $this->storeAsset($band, $file, 'photo');
    }

    private function storeAsset(Band $band, UploadedFile $file, string $kind): string
    {
        $disk = $this->disk();
        $prefix = $this->storagePrefix($band);
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $filename = $kind.'-'.now()->format('YmdHisv').'.'.$extension;
        $path = $prefix.'/'.$filename;

        Storage::disk($disk)->putFileAs($prefix, $file, $filename);

        return $path;
    }
}
