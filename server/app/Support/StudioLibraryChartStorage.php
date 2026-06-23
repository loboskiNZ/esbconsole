<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioLibraryChartStorage
{
    public function diskRoot(): string
    {
        $root = config('portal.library_storage_root')
            ?: config('filesystems.disks.library.root')
            ?: storage_path('app/library');

        return rtrim((string) $root, '/');
    }

    /**
     * Resolve a governed storage_reference to a path relative to the library disk root.
     */
    public function diskRelativePath(string $storageReference): string
    {
        $reference = ltrim($storageReference, '/');
        $root = $this->diskRoot();

        if (str_ends_with($root, '/charts') && str_starts_with($reference, 'charts/')) {
            return substr($reference, strlen('charts/'));
        }

        return $reference;
    }

    public function absolutePath(string $storageReference): string
    {
        return $this->diskRoot().'/'.$this->diskRelativePath($storageReference);
    }

    public function exists(string $storageReference): bool
    {
        if ($storageReference === '') {
            return false;
        }

        $absolutePath = $this->absolutePath($storageReference);

        if (is_readable($absolutePath)) {
            return true;
        }

        try {
            return Storage::disk((string) config('portal.library_chart_disk', 'library'))
                ->exists($this->diskRelativePath($storageReference));
        } catch (\Throwable) {
            return false;
        }
    }

    public function response(string $storageReference, string $filename, string $mimeType): StreamedResponse
    {
        return Storage::disk((string) config('portal.library_chart_disk', 'library'))
            ->response($this->diskRelativePath($storageReference), $filename, [
                'Content-Type' => $mimeType,
            ]);
    }
}
