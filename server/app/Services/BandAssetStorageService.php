<?php

namespace App\Services;

use App\Models\Band;
use App\Support\CloudStudioMediaStorage;
use Illuminate\Http\UploadedFile;

class BandAssetStorageService
{
    public function __construct(
        private readonly CloudStudioMediaStorage $mediaStorage,
    ) {}

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

    public function storeHeroPhoto(Band $band, UploadedFile $file): string
    {
        return $this->storeAsset($band, $file, 'hero');
    }

    public function storePressPhoto(Band $band, UploadedFile $file): string
    {
        return $this->storeAsset($band, $file, 'press');
    }

    private function storeAsset(Band $band, UploadedFile $file, string $kind): string
    {
        $prefix = $this->storagePrefix($band);
        $extension = strtolower($file->extension() ?: $file->guessExtension() ?: 'jpg');
        $filename = $kind.'-'.now()->format('YmdHisv').'.'.$extension;
        $path = $prefix.'/'.$filename;
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new \RuntimeException('Unable to read uploaded band asset.');
        }

        $this->mediaStorage->put($path, $contents);

        return $path;
    }
}
