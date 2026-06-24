<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\File;

class RecoveryFileResolutionService
{
  /** @return array{path: ?string, resolution_class: string, attempted_roots: list<string>, storage_reference_raw: string} */
  public function resolve(string $storageReference, string $domain, bool $required = true): array
  {
    $storageReference = trim($storageReference);
    $attempted = [];

    if ($storageReference === '') {
      return $this->result(null, $required ? 'required_missing' : 'optional_missing', [], $storageReference);
    }

    if (str_starts_with($storageReference, '/') && str_contains($storageReference, '/forge/')) {
      return $this->result(null, 'path_mismatch', [$storageReference], $storageReference);
    }

    if (str_starts_with($storageReference, '/') && File::exists($storageReference)) {
      return $this->result($storageReference, 'resolved', [$storageReference], $storageReference);
    }

    foreach ($this->rootsForDomain($domain) as $root) {
      $attempted[] = $root;
      $candidate = rtrim($root, '/').'/'.ltrim($storageReference, '/');
      if (File::exists($candidate)) {
        return $this->result($candidate, 'resolved', $attempted, $storageReference);
      }
    }

    if (str_starts_with($storageReference, '/')) {
      return $this->result(null, 'operator_action_required', $attempted, $storageReference);
    }

    return $this->result(null, $required ? 'required_missing' : 'optional_missing', $attempted, $storageReference);
  }

  /** @return list<string> */
  private function rootsForDomain(string $domain): array
  {
    $roots = array_filter([
      config('recovery.storage_roots.chart'),
      config('recovery.storage_roots.snippet'),
      config('recovery.storage_roots.profile'),
      config('recovery.storage_roots.ableton'),
      config('recovery.storage_roots.portal_library'),
    ]);

    return match ($domain) {
      'charts' => array_values(array_filter([
        config('recovery.storage_roots.chart'),
        config('recovery.storage_roots.portal_library'),
      ])),
      'snippets' => array_values(array_filter([config('recovery.storage_roots.snippet')])),
      'people_profiles', 'person_files' => array_values(array_filter([config('recovery.storage_roots.profile')])),
      'ableton_show_files' => array_values(array_filter([config('recovery.storage_roots.ableton')])),
      default => array_values($roots),
    };
  }

  /** @param  list<string>  $attempted */
  private function result(?string $path, string $class, array $attempted, string $raw): array
  {
    return [
      'path' => $path,
      'resolution_class' => $class,
      'attempted_roots' => $attempted,
      'storage_reference_raw' => $raw,
    ];
  }

  /** @param  list<array<string, mixed>>  $files */
  public function buildMissingFilesReport(string $batchId, array $files): array
  {
    $byClass = [
      'required_missing' => [],
      'optional_missing' => [],
      'orphaned_db_row' => [],
      'path_mismatch' => [],
      'operator_action_required' => [],
    ];

    foreach ($files as $file) {
      $class = $file['resolution_class'] ?? 'required_missing';
      if (! isset($byClass[$class])) {
        $byClass[$class] = [];
      }
      $byClass[$class][] = [
        'destination_key' => $file['destination_key'] ?? null,
        'storage_reference_raw' => $file['storage_reference_raw'] ?? null,
        'public_id' => $file['public_id'] ?? null,
      ];
    }

    $report = [
      'version' => 1,
      'schema' => 'esb.recovery.missing_files_report/v1',
      'batch_id' => $batchId,
      'generated_at' => now()->toIso8601String(),
      'summary' => array_map('count', $byClass),
      'by_class' => $byClass,
    ];

    return $report;
  }
}
