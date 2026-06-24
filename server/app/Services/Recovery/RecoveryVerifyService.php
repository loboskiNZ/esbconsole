<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoveryVerifyService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
  ) {}

  /** @return array<string, mixed> */
  public function verify(
    string $batchId,
    string $sourceConnection,
    string $targetConnection,
  ): array {
    $rowCounts = [];
    $fkOrphans = [];
    $warnings = [];
    $duplicatePublicIds = [];
    $entityMapIssues = [];
    $fileIssues = [];

    foreach ($this->registry->all() as $domain) {
      foreach ($domain['tables'] as $table) {
        $sourceCount = $this->tableCount($sourceConnection, $table);
        $targetCount = $this->tableCount($targetConnection, $table);

        $rowCounts[$table] = [
          'source' => $sourceCount,
          'cloud' => $targetCount,
          'match' => $sourceCount === $targetCount,
        ];
      }
    }

    $entityMap = $this->tryReadEntityMap($batchId);
    if ($entityMap === null) {
      $entityMapIssues[] = 'entity_map.json missing';
    } else {
      $seen = [];
      foreach ($entityMap['entries'] ?? [] as $entry) {
        $publicId = $entry['public_id'] ?? null;
        if ($publicId === null) {
          continue;
        }
        if (isset($seen[$publicId])) {
          $duplicatePublicIds[] = $publicId;
        }
        $seen[$publicId] = true;

        if (($entry['cloud_id'] ?? null) === null && ($entry['bigint_remap_ready'] ?? false) === true) {
          $entityMapIssues[] = 'cloud_id_pending_for_public_id:'.$publicId;
        }
      }

      if (! empty($entityMap['duplicate_public_ids'] ?? [])) {
        $warnings[] = 'transform reported duplicate public_id candidates';
      }
    }

    $fileManifest = $this->tryReadFileManifest($batchId);
    if ($fileManifest === null) {
      $warnings[] = 'file_manifest.json missing';
    } else {
      foreach ($fileManifest['files'] ?? [] as $file) {
        if (($file['status'] ?? '') === 'missing' && ($file['required'] ?? false)) {
          $fileIssues[] = $file['destination_key'] ?? 'unknown';
        }
      }
    }

    $exportManifest = $this->tryReadExportManifest($batchId);
    if ($exportManifest !== null) {
      foreach ($exportManifest['domains'] ?? [] as $domain) {
        $bundle = $domain['bundle_path'] ?? null;
        if ($bundle === null) {
          continue;
        }
        $path = $this->storage->batchPath($batchId).'/'.$bundle;
        if (! is_file($path) && ($domain['row_count'] ?? 0) > 0) {
          $warnings[] = "bundle missing for {$domain['domain']}";
        }
      }
    }

    $fkOrphans = $this->detectSimpleOrphans($targetConnection);

    $passed = empty($duplicatePublicIds)
      && empty($fileIssues)
      && ! collect($rowCounts)->contains(fn (array $c) => ($c['match'] ?? false) === false);

    $report = [
      'version' => 1,
      'schema' => 'esb.recovery.verification_report/v1',
      'batch_id' => $batchId,
      'verified_at' => now()->toIso8601String(),
      'passed' => $passed,
      'row_counts' => $rowCounts,
      'fk_orphans' => $fkOrphans,
      'duplicate_public_ids' => array_values(array_unique($duplicatePublicIds)),
      'entity_map_issues' => $entityMapIssues,
      'checksum_mismatches' => [],
      'missing_files' => $fileIssues,
      'warnings' => $warnings,
      'gate4_eligible' => $passed && empty($entityMapIssues),
    ];

    $this->storage->writeJson($batchId, 'verification_report.json', $report);

    return $report;
  }

  private function tableCount(string $connection, string $table): int
  {
    if (! Schema::connection($connection)->hasTable($table)) {
      return 0;
    }

    return (int) DB::connection($connection)->table($table)->count();
  }

  /** @return list<array<string, mixed>> */
  private function detectSimpleOrphans(string $connection): array
  {
    $orphans = [];

    if (! Schema::connection($connection)->hasTable('songs') || ! Schema::connection($connection)->hasTable('bands')) {
      return $orphans;
    }

    $rows = DB::connection($connection)
      ->table('songs as s')
      ->leftJoin('bands as b', 's.band_id', '=', 'b.id')
      ->whereNull('b.id')
      ->limit(20)
      ->get(['s.id as song_id', 's.band_id']);

    foreach ($rows as $row) {
      $orphans[] = [
        'table' => 'songs',
        'column' => 'band_id',
        'row_id' => $row->song_id ?? null,
        'missing_parent' => $row->band_id ?? null,
      ];
    }

    return $orphans;
  }

  /** @return array<string, mixed>|null */
  private function tryReadEntityMap(string $batchId): ?array
  {
    try {
      return $this->storage->readJson($batchId, 'entity_map.json');
    } catch (\Throwable) {
      return null;
    }
  }

  /** @return array<string, mixed>|null */
  private function tryReadFileManifest(string $batchId): ?array
  {
    try {
      return $this->storage->readJson($batchId, 'file_manifest.json');
    } catch (\Throwable) {
      return null;
    }
  }

  /** @return array<string, mixed>|null */
  private function tryReadExportManifest(string $batchId): ?array
  {
    try {
      return $this->storage->readJson($batchId, 'export_manifest.json');
    } catch (\Throwable) {
      return null;
    }
  }
}
