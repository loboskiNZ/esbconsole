<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoveryRollbackService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
  ) {}

  /** @return array<string, mixed> */
  public function buildRollbackReport(string $batchId, string $targetConnection, bool $dryRun): array
  {
    $tables = [];
    $mapRows = 0;

    $reversed = array_reverse($this->registry->all());

    foreach ($reversed as $domain) {
      foreach ($domain['tables'] as $table) {
        $count = 0;
        if (Schema::connection($targetConnection)->hasTable($table)) {
          $count = (int) DB::connection($targetConnection)->table($table)->count();
        }

        $tables[] = [
          'table_name' => $table,
          'would_delete_rows' => $count,
          'deleted_rows' => $dryRun ? 0 : 0,
          'action' => 'delete_by_batch_map',
        ];
      }
    }

    if (Schema::connection($targetConnection)->hasTable('cloud_recovery_entity_map')) {
      $mapRows = (int) DB::connection($targetConnection)->table('cloud_recovery_entity_map')->count();
    }

    $importManifest = $this->tryReadImportManifest($batchId);
    $filesDeleted = 0;
    if ($importManifest !== null) {
      foreach ($importManifest['domains'] ?? [] as $domain) {
        $filesDeleted += (int) ($domain['inserted'] ?? 0);
      }
    }

    $report = [
      'version' => 1,
      'schema' => 'esb.recovery.rollback_report/v1',
      'batch_id' => $batchId,
      'rolled_back_at' => now()->toIso8601String(),
      'dry_run' => $dryRun,
      'executed' => false,
      'instructions' => [
        'Delete imported rows in reverse dependency order using cloud_recovery_entity_map.',
        'Remove uploaded files listed in file_manifest.json destination_key entries.',
        'Archive rollback_report.json to incident log.',
      ],
      'tables' => $tables,
      'files_would_delete' => $filesDeleted,
      'files_deleted' => 0,
      'map_rows_would_delete' => $mapRows,
      'map_rows_deleted' => 0,
      'complete' => false,
    ];

    $this->storage->writeJson($batchId, 'rollback_report.json', $report);

    return $report;
  }

  /** @return array<string, mixed>|null */
  private function tryReadImportManifest(string $batchId): ?array
  {
    try {
      return $this->storage->readJson($batchId, 'import_manifest.json');
    } catch (\Throwable) {
      return null;
    }
  }
}
