<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\File;

class RecoveryImportService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
    private readonly RecoveryImportExecutor $executor,
  ) {}

  /** @return array<string, mixed> */
  public function import(string $batchId, bool $dryRun, ?string $sourceConnection = null, ?string $targetConnection = null): array
  {
    if (! $dryRun) {
      return $this->executor->execute(
        $batchId,
        $sourceConnection ?? (string) config('recovery.source_connection'),
        $targetConnection ?? (string) config('recovery.target_connection'),
      );
    }

    $domains = [];

    foreach ($this->registry->all() as $domain) {
      $bundlePath = $this->storage->domainBundlePath($batchId, $domain['key']);
      $inserted = 0;
      $skipped = 0;

      if (! File::exists($bundlePath) && ! config('recovery.rehearsal_mode')) {
        $domains[] = [
          'domain' => $domain['key'],
          'inserted' => 0,
          'skipped' => 0,
          'errors' => [],
        ];

        continue;
      }

      if (File::exists($bundlePath)) {
        foreach (File::lines($bundlePath) as $line) {
          $row = json_decode($line, true);
          if (! is_array($row)) {
            $skipped++;
            continue;
          }
          $inserted++;
        }
      } else {
        $inserted = 0;
      }

      $domains[] = [
        'domain' => $domain['key'],
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => [],
      ];
    }

    $manifest = [
      'version' => 1,
      'schema' => 'esb.recovery.import_manifest/v1',
      'batch_id' => $batchId,
      'imported_at' => now()->toIso8601String(),
      'dry_run' => true,
      'domains' => $domains,
    ];

    $this->storage->writeJson($batchId, 'import_manifest.json', $manifest);

    return $manifest;
  }
}
