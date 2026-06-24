<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class RecoveryDeferredForeignKeyService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
  ) {}

  /** @return list<array<string, mixed>> */
  public function captureFromBandsBundle(string $batchId): array
  {
    $bundlePath = $this->storage->domainBundlePath($batchId, 'bands');
    $entries = [];

    if (! File::exists($bundlePath)) {
      return $entries;
    }

    foreach (File::lines($bundlePath) as $line) {
      $row = json_decode($line, true);
      if (! is_array($row)) {
        continue;
      }

      $directorId = $row['primary_director_musician_id'] ?? null;
      if ($directorId === null || $directorId === '') {
        continue;
      }

      $entries[] = [
        'table' => 'bands',
        'column' => 'primary_director_musician_id',
        'row_source_id' => (int) ($row['id'] ?? 0),
        'referenced_table' => 'musicians',
        'referenced_source_id' => (int) $directorId,
        'public_id' => $row['public_id'] ?? null,
        'status' => 'queued',
      ];
    }

    return $entries;
  }

  /** @param  list<array<string, mixed>>  $entries */
  public function writeManifest(string $batchId, array $entries): array
  {
    $payload = [
      'version' => 1,
      'schema' => 'esb.recovery.deferred_fk/v1',
      'batch_id' => $batchId,
      'generated_at' => now()->toIso8601String(),
      'entries' => $entries,
    ];

    $this->storage->writeJson($batchId, 'deferred_fk.json', $payload);

    return $payload;
  }

  /** @return array<string, mixed> */
  public function loadManifest(string $batchId): array
  {
    try {
      return $this->storage->readJson($batchId, 'deferred_fk.json');
    } catch (\Throwable) {
      return [
        'version' => 1,
        'schema' => 'esb.recovery.deferred_fk/v1',
        'batch_id' => $batchId,
        'entries' => [],
      ];
    }
  }

  /**
   * @param  array<string, array<int, int>>  $idMap
   * @return array<string, mixed>
   */
  public function replay(string $batchId, string $targetConnection, array $idMap): array
  {
    $manifest = $this->loadManifest($batchId);
    $applied = 0;
    $unresolved = 0;
    $patches = [];

    foreach ($manifest['entries'] ?? [] as $entry) {
      $bandSourceId = (int) ($entry['row_source_id'] ?? 0);
      $musicianSourceId = (int) ($entry['referenced_source_id'] ?? 0);
      $bandCloudId = $idMap['bands'][$bandSourceId] ?? null;
      $musicianCloudId = $idMap['musicians'][$musicianSourceId] ?? null;

      if ($bandCloudId === null || $musicianCloudId === null) {
        $unresolved++;
        $patches[] = [
          'row_source_id' => $bandSourceId,
          'referenced_source_id' => $musicianSourceId,
          'status' => 'unresolved',
        ];
        continue;
      }

      DB::connection($targetConnection)
        ->table('bands')
        ->where('id', $bandCloudId)
        ->update(['primary_director_musician_id' => $musicianCloudId]);

      $applied++;
      $patches[] = [
        'row_source_id' => $bandSourceId,
        'cloud_band_id' => $bandCloudId,
        'referenced_source_id' => $musicianSourceId,
        'cloud_musician_id' => $musicianCloudId,
        'status' => 'applied',
      ];
    }

    $report = [
      'version' => 1,
      'schema' => 'esb.recovery.deferred_fk_report/v1',
      'batch_id' => $batchId,
      'replayed_at' => now()->toIso8601String(),
      'queued' => count($manifest['entries'] ?? []),
      'applied' => $applied,
      'unresolved' => $unresolved,
      'complete' => $unresolved === 0,
      'patches' => $patches,
    ];

    $this->storage->writeJson($batchId, 'deferred_fk_report.json', $report);

    return $report;
  }

  /** @param  array<string, mixed>  $row */
  public function deferBandDirectorColumn(array $row): array
  {
    if (array_key_exists('primary_director_musician_id', $row)) {
      $row['primary_director_musician_id'] = null;
    }

    return $row;
  }
}
