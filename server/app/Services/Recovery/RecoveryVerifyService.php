<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoveryVerifyService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
    private readonly RecoveryDeferredForeignKeyService $deferredFk,
  ) {}

  /** @return array<string, mixed> */
  public function verify(
    string $batchId,
    string $sourceConnection,
    string $targetConnection,
  ): array {
    $rowCounts = [];
    $duplicatePublicIds = [];
    $entityMapIssues = [];
    $fileIssues = [];
    $warnings = [];

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

    $entityMap = $this->tryReadJson($batchId, 'entity_map.json');
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
      }
    }

    $deferredFkStats = $this->buildDeferredFkStats($batchId);
    $effectTransformStats = $this->buildEffectTransformStats($batchId);
    $fileResolutionStats = $this->buildFileResolutionStats($batchId, $fileIssues);

    $fkOrphans = $this->detectSimpleOrphans($targetConnection);

    $importManifest = $this->tryReadJson($batchId, 'import_manifest.json');
    $cascadeBlocked = collect($importManifest['domains'] ?? [])
      ->contains(fn (array $d) => ($d['blocked'] ?? false) === true);

    $gate4Blockers = [];
    if ($deferredFkStats['unresolved'] > 0) {
      $gate4Blockers[] = 'deferred_fk_unresolved';
    }
    if ($fileResolutionStats['required_missing'] > 0) {
      $gate4Blockers[] = 'required_files_missing';
    }
    if (collect($rowCounts)->contains(fn (array $c) => ($c['match'] ?? false) === false)) {
      $gate4Blockers[] = 'row_count_mismatch';
    }
    if ($cascadeBlocked) {
      $gate4Blockers[] = 'dependency_cascade_blocked';
    }
    if (($effectTransformStats['ambiguous_count'] ?? 0) > 0) {
      $gate4Blockers[] = 'effect_transform_ambiguous';
    }

    $passed = empty($duplicatePublicIds)
      && empty($fileIssues)
      && $deferredFkStats['unresolved'] === 0
      && ! $cascadeBlocked
      && ! collect($rowCounts)->contains(fn (array $c) => ($c['match'] ?? false) === false);

    $gate4Eligible = $passed && empty($entityMapIssues);

    $report = [
      'version' => 2,
      'schema' => 'esb.recovery.verification_report/v2',
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
      'gate4_eligible' => $gate4Eligible,
      'deferred_fk' => $deferredFkStats,
      'effect_transform' => $effectTransformStats,
      'file_resolution' => $fileResolutionStats,
      'gate4_readiness' => [
        'eligible' => $gate4Eligible,
        'blockers' => $gate4Blockers,
      ],
    ];

    $this->storage->writeJson($batchId, 'verification_report.json', $report);

    return $report;
  }

  /** @return array<string, int|bool> */
  private function buildDeferredFkStats(string $batchId): array
  {
    $manifest = $this->deferredFk->loadManifest($batchId);
    $report = $this->tryReadJson($batchId, 'deferred_fk_report.json');

    return [
      'queued' => count($manifest['entries'] ?? []),
      'applied' => (int) ($report['applied'] ?? 0),
      'unresolved' => (int) ($report['unresolved'] ?? count($manifest['entries'] ?? [])),
      'complete' => (bool) ($report['complete'] ?? false),
    ];
  }

  /** @return array<string, mixed> */
  private function buildEffectTransformStats(string $batchId): array
  {
    $report = $this->tryReadJson($batchId, 'effect_transform_report.json');

    if ($report === null) {
      return [
        'transformed_count' => 0,
        'skipped_count' => 0,
        'ambiguous_count' => 0,
        'present' => false,
      ];
    }

    return [
      'transformed_count' => (int) ($report['transformed_count'] ?? 0),
      'skipped_count' => (int) ($report['skipped_count'] ?? 0),
      'ambiguous_count' => (int) ($report['ambiguous_count'] ?? 0),
      'operator_review_count' => count($report['operator_review'] ?? []),
      'present' => true,
    ];
  }

  /** @param  list<string>  $fileIssues */
  private function buildFileResolutionStats(string $batchId, array &$fileIssues): array
  {
    $missingReport = $this->tryReadJson($batchId, 'missing_files_report.json');
    $summary = $missingReport['summary'] ?? [];

    foreach ($missingReport['by_class']['required_missing'] ?? [] as $item) {
      $fileIssues[] = $item['destination_key'] ?? 'unknown';
    }

    $resolved = 0;
    $manifest = $this->tryReadJson($batchId, 'file_manifest.json');
    foreach ($manifest['files'] ?? [] as $file) {
      if (($file['resolution_class'] ?? '') === 'resolved') {
        $resolved++;
      }
    }

    return [
      'resolved' => $resolved,
      'required_missing' => (int) ($summary['required_missing'] ?? 0),
      'optional_missing' => (int) ($summary['optional_missing'] ?? 0),
      'path_mismatch' => (int) ($summary['path_mismatch'] ?? 0),
      'operator_action_required' => (int) ($summary['operator_action_required'] ?? 0),
    ];
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
  private function tryReadJson(string $batchId, string $filename): ?array
  {
    try {
      return $this->storage->readJson($batchId, $filename);
    } catch (\Throwable) {
      return null;
    }
  }
}
