<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CloudStudioMediaStorage
{
    public function mediaDisk(): string
    {
        return (string) config('portal.media_disk', 'media');
    }

    public function chartReference(int $bandId, string $songCode, string $filename): string
    {
        return "library/charts/{$bandId}/{$songCode}/{$filename}";
    }

    public function chartDiskRoot(): string
    {
        $root = config('portal.library_storage_root')
            ?: config('filesystems.disks.library.root')
            ?: storage_path('app/library');

        return rtrim((string) $root, '/');
    }

    /**
     * Resolve a chart storage_reference to a path relative to the library disk root.
     */
    public function chartDiskRelativePath(string $storageReference): string
    {
        $reference = ltrim($storageReference, '/');
        $root = $this->chartDiskRoot();

        if (str_starts_with($reference, 'library/charts/')) {
            $reference = substr($reference, strlen('library/'));
        }

        if (str_ends_with($root, '/charts') && str_starts_with($reference, 'charts/')) {
            return substr($reference, strlen('charts/'));
        }

        return $reference;
    }

    /**
     * @return list<string>
     */
    public function s3KeysForReference(string $storageReference): array
    {
        $reference = ltrim($storageReference, '/');
        $keys = [$reference];

        if (str_starts_with($reference, 'charts/')) {
            $keys[] = 'library/'.$reference;
        }

        if (str_starts_with($reference, 'library/charts/')) {
            $legacy = substr($reference, strlen('library/'));
            if (! in_array($legacy, $keys, true)) {
                $keys[] = $legacy;
            }
        }

        return array_values(array_unique($keys));
    }

    public function legacyLocalDiskForReference(string $storageReference): ?string
    {
        $reference = ltrim($storageReference, '/');

        if (str_starts_with($reference, 'library/charts/') || str_starts_with($reference, 'charts/')) {
            return (string) config('portal.library_chart_disk', 'library');
        }

        if (str_starts_with($reference, 'portal/profile-photos/')
            || str_starts_with($reference, 'portal/band-assets/')) {
            return 'local';
        }

        return null;
    }

    public function legacyLocalRelativePath(string $storageReference): string
    {
        $reference = ltrim($storageReference, '/');

        if (str_starts_with($reference, 'library/charts/') || str_starts_with($reference, 'charts/')) {
            return $this->chartDiskRelativePath($storageReference);
        }

        return $reference;
    }

    public function s3WriteKey(string $storageReference): string
    {
        $key = ltrim($storageReference, '/');

        if (str_starts_with($key, 'charts/')) {
            return 'library/'.$key;
        }

        return $key;
    }

    public function s3Configured(): bool
    {
        $disk = config('filesystems.disks.media', []);

        return filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null)
            && filled($disk['bucket'] ?? null);
    }

    public function exists(string $storageReference): bool
    {
        if ($storageReference === '') {
            return false;
        }

        if ($this->resolvedS3Key($storageReference) !== null) {
            return true;
        }

        $legacyDisk = $this->legacyLocalDiskForReference($storageReference);

        if ($legacyDisk === null) {
            return false;
        }

        try {
            return Storage::disk($legacyDisk)
                ->exists($this->legacyLocalRelativePath($storageReference));
        } catch (\Throwable) {
            return false;
        }
    }

    public function resolvedS3Key(string $storageReference): ?string
    {
        try {
            $mediaDisk = Storage::disk($this->mediaDisk());

            foreach ($this->s3KeysForReference($storageReference) as $key) {
                if ($mediaDisk->exists($key)) {
                    return $key;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    public function put(string $storageReference, string $contents): void
    {
        $key = $this->s3WriteKey($storageReference);
        $mediaDiskName = $this->mediaDisk();

        try {
            if (Storage::disk($mediaDiskName)->put($key, $contents)) {
                return;
            }
        } catch (\Throwable) {
            if ($this->s3Configured()) {
                throw new RuntimeException("Unable to write media object at {$key}.");
            }
        }

        if ($this->s3Configured()) {
            throw new RuntimeException("Unable to write media object at {$key}.");
        }

        $this->putLocal($storageReference, $contents);
    }

    public function putLocal(string $storageReference, string $contents): void
    {
        $legacyDisk = $this->legacyLocalDiskForReference($storageReference);

        if ($legacyDisk === null) {
            throw new RuntimeException("No local disk configured for {$storageReference}.");
        }

        $relative = $this->legacyLocalRelativePath($storageReference);
        $disk = Storage::disk($legacyDisk);

        $this->ensureLocalDirectory($disk, $relative);

        if (! $disk->put($relative, $contents)) {
            throw new RuntimeException("Unable to write local media object at {$relative}.");
        }

        $this->normalizeLocalPathPermissions($disk, $relative);
    }

    public function delete(string $storageReference): void
    {
        if ($storageReference === '') {
            return;
        }

        if ($this->s3Configured()) {
            try {
                foreach ($this->s3KeysForReference($storageReference) as $key) {
                    Storage::disk($this->mediaDisk())->delete($key);
                }
            } catch (\Throwable) {
                //
            }
        }

        $legacyDisk = $this->legacyLocalDiskForReference($storageReference);

        if ($legacyDisk === null) {
            return;
        }

        try {
            Storage::disk($legacyDisk)->delete($this->legacyLocalRelativePath($storageReference));
        } catch (\Throwable) {
            //
        }
    }

    public function response(string $storageReference, string $filename, ?string $mimeType = null): StreamedResponse
    {
        $headers = array_filter(['Content-Type' => $mimeType]);

        $s3Key = $this->resolvedS3Key($storageReference);

        if ($s3Key !== null) {
            return Storage::disk($this->mediaDisk())->response($s3Key, $filename, $headers);
        }

        $legacyDisk = $this->legacyLocalDiskForReference($storageReference);

        if ($legacyDisk !== null) {
            $relative = $this->legacyLocalRelativePath($storageReference);

            if (Storage::disk($legacyDisk)->exists($relative)) {
                return Storage::disk($legacyDisk)->response($relative, $filename, $headers);
            }
        }

        abort(404);
    }

    private function ensureLocalDirectory(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $relativePath): void
    {
        $directory = trim(str_replace('\\', '/', dirname($relativePath)), '/.');

        if ($directory === '') {
            return;
        }

        if ($disk->exists($directory)) {
            $this->normalizeLocalPathPermissions($disk, $directory);

            return;
        }

        $disk->makeDirectory($directory);
        $this->normalizeLocalPathPermissions($disk, $directory);
    }

    private function normalizeLocalPathPermissions(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $relativePath): void
    {
        try {
            $absolute = $disk->path($relativePath);

            if (is_dir($absolute)) {
                @chmod($absolute, 0775);
            } elseif (is_file($absolute)) {
                @chmod($absolute, 0664);
            }
        } catch (\Throwable) {
            //
        }
    }
}
