<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class RecoveryExportService
{
  public function __construct(
    private readonly RecoveryDomainRegistry $registry,
    private readonly RecoveryBatchStorage $storage,
  ) {}

  /** @return array<string, mixed> */
  public function exportDomain(string $batchId, string $sourceConnection, bool $dryRun): array
  {
    $domains = [];
    $exportedAt = now()->toIso8601String();

    foreach ($this->registry->exportable() as $domain) {
      $rowCount = 0;
      $bundlePath = 'domains/'.$domain['key'].'.jsonl';
      $absoluteBundle = $this->storage->domainBundlePath($batchId, $domain['key']);

      if (! $dryRun) {
        File::ensureDirectoryExists(dirname($absoluteBundle));
        File::put($absoluteBundle, '');
      }

      foreach ($domain['tables'] as $table) {
        if (! Schema::connection($sourceConnection)->hasTable($table)) {
          continue;
        }

        DB::connection($sourceConnection)->table($table)->orderBy('id')->chunk(200, function ($rows) use (
          $dryRun,
          $absoluteBundle,
          $table,
          &$rowCount,
        ) {
          foreach ($rows as $row) {
            $rowCount++;
            if (! $dryRun) {
              $payload = (array) $row;
              $payload['_recovery_table'] = $table;
              File::append($absoluteBundle, json_encode($payload, JSON_UNESCAPED_SLASHES)."\n");
            }
          }
        });
      }

      $checksum = null;
      if (! $dryRun && File::exists($absoluteBundle)) {
        $checksum = hash_file('sha256', $absoluteBundle);
      }

      $domains[] = [
        'domain' => $domain['key'],
        'tables' => $domain['tables'],
        'row_count' => $rowCount,
        'bundle_path' => $bundlePath,
        'checksum_sha256' => $checksum,
      ];
    }

    return [
      'version' => 1,
      'schema' => 'esb.recovery.export_manifest/v1',
      'batch_id' => $batchId,
      'source_env' => config('recovery.source_env', 'live_stage'),
      'exported_at' => $exportedAt,
      'dry_run' => $dryRun,
      'domains' => $domains,
    ];
  }
}
