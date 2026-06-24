<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\File;

class RecoveryImportService
{
  public function __construct(
    private readonly RecoveryBatchStorage $storage,
    private readonly RecoveryDomainRegistry $registry,
  ) {}

  /** @return array<string, mixed> */
  public function import(string $batchId, bool $dryRun): array
  {
    $domains = [];

    foreach ($this->registry->all() as $domain) {
      $bundlePath = $this->storage->domainBundlePath($batchId, $domain['key']);
      $inserted = 0;
      $skipped = 0;
      $errors = [];

      if (! File::exists($bundlePath)) {
        $domains[] = [
          'domain' => $domain['key'],
          'inserted' => 0,
          'skipped' => 0,
          'errors' => $dryRun ? [] : ['bundle_missing'],
        ];

        continue;
      }

      foreach (File::lines($bundlePath) as $line) {
        $row = json_decode($line, true);
        if (! is_array($row)) {
          $skipped++;
          continue;
        }

        if ($dryRun) {
          $inserted++;
          continue;
        }

        $errors[] = 'write_execution_disabled_in_ph067a';
        $skipped++;
      }

      $domains[] = [
        'domain' => $domain['key'],
        'inserted' => $inserted,
        'skipped' => $skipped,
        'errors' => $errors,
      ];
    }

    $manifest = [
      'version' => 1,
      'schema' => 'esb.recovery.import_manifest/v1',
      'batch_id' => $batchId,
      'imported_at' => now()->toIso8601String(),
      'dry_run' => $dryRun,
      'domains' => $domains,
    ];

    $this->storage->writeJson($batchId, 'import_manifest.json', $manifest);

    return $manifest;
  }
}
