<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class StudioLibraryChartStorage
{
    public function __construct(
        private readonly CloudStudioMediaStorage $mediaStorage,
    ) {}

    public function diskRoot(): string
    {
        return $this->mediaStorage->chartDiskRoot();
    }

    /**
     * Resolve a governed storage_reference to a path relative to the library disk root.
     */
    public function diskRelativePath(string $storageReference): string
    {
        return $this->mediaStorage->chartDiskRelativePath($storageReference);
    }

    public function absolutePath(string $storageReference): string
    {
        return $this->diskRoot().'/'.$this->diskRelativePath($storageReference);
    }

    public function exists(string $storageReference): bool
    {
        return $this->mediaStorage->exists($storageReference);
    }

    public function response(string $storageReference, string $filename, string $mimeType): StreamedResponse
    {
        return $this->mediaStorage->response($storageReference, $filename, $mimeType);
    }
}
