<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\File;

class RecoveryFileManifestService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
    private readonly RecoveryFileResolutionService $fileResolution,
  ) {}

  /** @return array<string, mixed> */
  public function buildFileManifest(string $batchId, string $sourceConnection, bool $dryRun): array
  {
    $files = [];

    foreach ($this->registry->fileDomains() as $domain) {
      $files = array_merge($files, $this->collectDomainFiles($batchId, $domain));
    }

    $manifest = [
      'version' => 1,
      'schema' => 'esb.recovery.files_manifest/v1',
      'batch_id' => $batchId,
      'generated_at' => now()->toIso8601String(),
      'dry_run' => $dryRun,
      'files' => $files,
    ];

    $missingReport = $this->fileResolution->buildMissingFilesReport($batchId, $files);
    $this->storage->writeJson($batchId, 'missing_files_report.json', $missingReport);

    return $manifest;
  }

  /** @return list<array<string, mixed>> */
  private function collectDomainFiles(string $batchId, string $domain): array
  {
    $bundlePath = $this->storage->domainBundlePath($batchId, $domain);

    if (! File::exists($bundlePath)) {
      return $this->placeholderEntries($domain);
    }

    $entries = [];

    foreach (File::lines($bundlePath) as $line) {
      $row = json_decode($line, true);
      if (! is_array($row)) {
        continue;
      }

      $required = $domain === 'charts';
      $rawReference = $this->rawStorageReference($row, $domain);
      if ($rawReference === null) {
        continue;
      }

      $resolved = $this->fileResolution->resolve($rawReference, $domain, $required);
      $path = $resolved['path'];
      $exists = $path !== null && File::exists($path);
      $bytes = $exists ? File::size($path) : 0;
      $sha256 = $exists ? hash_file('sha256', $path) : null;

      $entries[] = [
        'entity' => $domain,
        'public_id' => $row['public_id'] ?? null,
        'path' => $path,
        'source_path' => $path,
        'storage_reference_raw' => $resolved['storage_reference_raw'],
        'destination_key' => $this->destinationKey($domain, $row),
        'sha256' => $sha256,
        'bytes' => $bytes,
        'size' => $bytes,
        'domain' => $domain,
        'required' => $required,
        'resolution_class' => $resolved['resolution_class'],
        'attempted_roots' => $resolved['attempted_roots'],
        'status' => $exists ? 'pending' : 'missing',
      ];
    }

    return $entries;
  }

  /** @param  array<string, mixed>  $row */
  private function rawStorageReference(array $row, string $domain): ?string
  {
    $candidates = match ($domain) {
      'charts' => [$row['storage_reference'] ?? null, $row['file_path'] ?? null],
      'snippets' => [$row['storage_reference'] ?? null, $row['audio_storage_reference'] ?? null, $row['midi_storage_reference'] ?? null],
      'people_profiles' => [$row['profile_image_path'] ?? null],
      'person_files' => [$row['storage_reference'] ?? null, $row['file_path'] ?? null],
      'ableton_show_files' => [$row['storage_reference'] ?? null],
      default => [$row['storage_reference'] ?? null],
    };

    foreach ($candidates as $candidate) {
      if (is_string($candidate) && $candidate !== '') {
        return $candidate;
      }
    }

    return null;
  }

  /** @param  array<string, mixed>  $row */
  private function destinationKey(string $domain, array $row): string
  {
    $publicId = (string) ($row['public_id'] ?? 'unknown');

    return match ($domain) {
      'charts' => "charts/{$publicId}/file",
      'snippets' => "snippets/{$publicId}/asset",
      'people_profiles' => "people/{$publicId}/profile",
      'person_files' => "people/{$publicId}/file",
      'ableton_show_files' => "ableton/{$publicId}/show",
      default => "{$domain}/{$publicId}",
    };
  }

  /** @return list<array<string, mixed>> */
  private function placeholderEntries(string $domain): array
  {
    return [[
      'entity' => $domain,
      'public_id' => null,
      'path' => null,
      'source_path' => null,
      'storage_reference_raw' => null,
      'destination_key' => "{$domain}/pending",
      'sha256' => null,
      'bytes' => 0,
      'size' => 0,
      'domain' => $domain,
      'required' => false,
      'resolution_class' => 'optional_missing',
      'attempted_roots' => [],
      'status' => 'missing',
    ]];
  }

  /** @return array<string, mixed> */
  public function buildUploadPlan(string $batchId): array
  {
    $manifest = $this->storage->readJson($batchId, 'file_manifest.json');

    $plan = [];
    foreach ($manifest['files'] ?? [] as $file) {
      $plan[] = [
        'destination_key' => $file['destination_key'] ?? null,
        'source_path' => $file['source_path'] ?? $file['path'] ?? null,
        'sha256' => $file['sha256'] ?? null,
        'bytes' => $file['bytes'] ?? $file['size'] ?? 0,
        'action' => ($file['status'] ?? 'missing') === 'missing' ? 'skip' : 'upload',
        'resolution_class' => $file['resolution_class'] ?? null,
        'spaces_connected' => false,
      ];
    }

    return [
      'version' => 1,
      'schema' => 'esb.recovery.upload_plan/v1',
      'batch_id' => $batchId,
      'generated_at' => now()->toIso8601String(),
      'dry_run' => true,
      'entries' => $plan,
    ];
  }
}
