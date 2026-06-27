<?php

namespace App\Services;

use App\Models\Band;
use App\Models\Library\Chart;
use App\Models\Person;
use App\Support\CloudStudioMediaStorage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CloudStudioMediaMigrationService
{
    public function __construct(
        private readonly CloudStudioMediaStorage $mediaStorage,
    ) {}

    /**
     * @return Collection<int, array{
     *     media_type: string,
     *     table: string,
     *     record_id: int,
     *     column: string,
     *     storage_reference: string,
     * }>
     */
    public function discover(): Collection
    {
        $entries = collect();

        Chart::query()
            ->whereNotNull('storage_reference')
            ->where('storage_reference', '!=', '')
            ->orderBy('id')
            ->each(function (Chart $chart) use ($entries): void {
                $reference = trim((string) $chart->storage_reference);

                if (! $this->isManagedReference($reference)) {
                    return;
                }

                $entries->push([
                    'media_type' => 'chart',
                    'table' => 'charts',
                    'record_id' => (int) $chart->id,
                    'column' => 'storage_reference',
                    'storage_reference' => $reference,
                ]);
            });

        Person::query()
            ->orderBy('id')
            ->each(function (Person $person) use ($entries): void {
                foreach ([
                    'profile_photo' => 'profile_photo_path',
                    'profile_photo_display' => 'profile_photo_display_path',
                ] as $mediaType => $column) {
                    $reference = trim((string) $person->{$column});

                    if ($reference === '' || ! $this->isManagedReference($reference)) {
                        continue;
                    }

                    $entries->push([
                        'media_type' => $mediaType,
                        'table' => 'people',
                        'record_id' => (int) $person->id,
                        'column' => $column,
                        'storage_reference' => $reference,
                    ]);
                }
            });

        Band::query()
            ->orderBy('id')
            ->each(function (Band $band) use ($entries): void {
                foreach ([
                    'band_logo' => 'logo_path',
                    'band_photo' => 'photo_path',
                    'band_hero' => 'hero_photo_path',
                    'band_press' => 'press_photo_path',
                ] as $mediaType => $column) {
                    $reference = trim((string) $band->{$column});

                    if ($reference === '' || ! $this->isManagedReference($reference)) {
                        continue;
                    }

                    $entries->push([
                        'media_type' => $mediaType,
                        'table' => 'bands',
                        'record_id' => (int) $band->id,
                        'column' => $column,
                        'storage_reference' => $reference,
                    ]);
                }
            });

        return $entries;
    }

    /**
     * @param  array{
     *     media_type: string,
     *     table: string,
     *     record_id: int,
     *     column: string,
     *     storage_reference: string,
     * }  $entry
     * @return array{
     *     media_type: string,
     *     record_id: int,
     *     table: string,
     *     column: string,
     *     old_path: string,
     *     new_s3_path: string,
     *     source_exists: bool,
     *     s3_copy_status: string,
     *     db_update_status: string,
     *     error: ?string,
     *     verified_s3: bool,
     *     serves_via_s3: bool,
     *     serves_via_local: bool,
     * }
     */
    public function migrateEntry(array $entry, bool $dryRun = false): array
    {
        $oldPath = $entry['storage_reference'];
        $targetKey = $this->mediaStorage->s3WriteKey($oldPath);
        $canonicalDbPath = $targetKey;

        $legacyDisk = $this->mediaStorage->legacyLocalDiskForReference($oldPath);
        $legacyRelative = $this->mediaStorage->legacyLocalRelativePath($oldPath);
        $sourceExists = $legacyDisk !== null
            && $this->localExists($legacyDisk, $legacyRelative);

        $s3ExistsBefore = $this->s3ObjectExists($targetKey, $oldPath);

        $result = [
            'media_type' => $entry['media_type'],
            'record_id' => $entry['record_id'],
            'table' => $entry['table'],
            'column' => $entry['column'],
            'old_path' => $oldPath,
            'new_s3_path' => $targetKey,
            'source_exists' => $sourceExists,
            's3_copy_status' => 'pending',
            'db_update_status' => 'pending',
            'error' => null,
            'verified_s3' => false,
            'serves_via_s3' => false,
            'serves_via_local' => $sourceExists,
        ];

        if ($s3ExistsBefore) {
            $result['s3_copy_status'] = 'already_present';
            $result['verified_s3'] = true;
        } elseif ($sourceExists) {
            if ($dryRun) {
                $result['s3_copy_status'] = 'would_copy';
            } else {
                try {
                    $contents = Storage::disk($legacyDisk)->get($legacyRelative);

                    if ($contents === null) {
                        throw new \RuntimeException('Unable to read local source file.');
                    }

                    if (! Storage::disk($this->mediaStorage->mediaDisk())->put($targetKey, $contents)) {
                        throw new \RuntimeException('S3 put returned false.');
                    }

                    if (! $this->s3ObjectExists($targetKey, $oldPath)) {
                        throw new \RuntimeException('S3 object missing after copy.');
                    }

                    $result['s3_copy_status'] = 'copied';
                    $result['verified_s3'] = true;
                } catch (\Throwable $exception) {
                    $result['s3_copy_status'] = 'failed';
                    $result['db_update_status'] = 'unchanged';
                    $result['error'] = $exception->getMessage();

                    return $this->finalizeServeFlags($result, $oldPath, $sourceExists);
                }
            }
        } else {
            if ($dryRun) {
                $result['s3_copy_status'] = 'missing_source';
                $result['db_update_status'] = 'unchanged';

                return $this->finalizeServeFlags($result, $oldPath, $sourceExists);
            }

            $result['s3_copy_status'] = 'failed';
            $result['db_update_status'] = 'unchanged';
            $result['error'] = 'Local source missing and S3 object not present.';

            return $this->finalizeServeFlags($result, $oldPath, $sourceExists);
        }

        $dbAlreadyCanonical = $oldPath === $canonicalDbPath;
        $s3Ready = $result['verified_s3'] || ($dryRun && ($s3ExistsBefore || $sourceExists));

        if (! $s3Ready) {
            $result['db_update_status'] = 'unchanged';

            return $this->finalizeServeFlags($result, $oldPath, $sourceExists);
        }

        if ($dbAlreadyCanonical) {
            $result['db_update_status'] = 'already_canonical';
        } elseif ($dryRun) {
            $result['db_update_status'] = 'would_update';
        } else {
            $updated = $this->updateDatabaseReference(
                $entry['table'],
                $entry['record_id'],
                $entry['column'],
                $canonicalDbPath,
            );

            $result['db_update_status'] = $updated ? 'updated' : 'update_failed';

            if (! $updated) {
                $result['error'] = 'Database reference update failed.';
            }
        }

        return $this->finalizeServeFlags($result, $canonicalDbPath, $sourceExists);
    }

    public function isManagedReference(string $reference): bool
    {
        $reference = ltrim($reference, '/');

        return str_starts_with($reference, 'charts/')
            || str_starts_with($reference, 'library/charts/')
            || str_starts_with($reference, 'portal/profile-photos/')
            || str_starts_with($reference, 'portal/band-assets/');
    }

    private function s3ObjectExists(string $targetKey, string $storageReference): bool
    {
        if ($this->mediaStorage->resolvedS3Key($storageReference) !== null) {
            return true;
        }

        try {
            return Storage::disk($this->mediaStorage->mediaDisk())->exists($targetKey);
        } catch (\Throwable) {
            return false;
        }
    }

    private function localExists(string $disk, string $relativePath): bool
    {
        try {
            return Storage::disk($disk)->exists($relativePath);
        } catch (\Throwable) {
            return false;
        }
    }

    private function updateDatabaseReference(string $table, int $recordId, string $column, string $path): bool
    {
        return match ($table) {
            'charts' => (bool) Chart::query()
                ->whereKey($recordId)
                ->where('storage_reference', '!=', $path)
                ->update(['storage_reference' => $path]),
            'people' => (bool) Person::query()
                ->whereKey($recordId)
                ->where($column, '!=', $path)
                ->update([$column => $path]),
            'bands' => (bool) Band::query()
                ->whereKey($recordId)
                ->where($column, '!=', $path)
                ->update([$column => $path]),
            default => false,
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function finalizeServeFlags(array $result, string $serveReference, bool $localStillExists): array
    {
        $result['serves_via_s3'] = $this->mediaStorage->resolvedS3Key($serveReference) !== null;
        $result['serves_via_local'] = $localStillExists
            && $this->mediaStorage->legacyLocalDiskForReference($serveReference) !== null
            && $this->localExists(
                (string) $this->mediaStorage->legacyLocalDiskForReference($serveReference),
                $this->mediaStorage->legacyLocalRelativePath($serveReference),
            );

        return $result;
    }
}
