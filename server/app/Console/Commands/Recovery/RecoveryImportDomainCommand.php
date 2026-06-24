<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryImportService;

class RecoveryImportDomainCommand extends RecoveryCommand
{
  protected $signature = 'recovery:import-domain
                            {--batch= : Recovery batch UUID (required)}
                            {--dry-run : Validate import plan only (default)}
                            {--execute : Attempt write execution (blocked without local acknowledgement)}';

  protected $description = 'Validate or import domain bundles into recovery target (dry-run default)';

  public function handle(RecoveryImportService $importer, RecoveryBatchStorage $storage): int
  {
    $dryRun = $this->resolveDryRun((bool) $this->option('execute'));

    try {
      $this->guard(! $dryRun);
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    if ($this->option('batch') === null) {
      $this->error('--batch is required for recovery:import-domain');

      return self::FAILURE;
    }

    $batchId = $storage->resolveBatchId($this->option('batch'));
    $manifest = $importer->import(
      $batchId,
      $dryRun,
      $this->sourceConnection(),
      $this->targetConnection(),
    );

    if (! $dryRun) {
      $storage->writeJson($batchId, 'import_manifest.json', $manifest);
    }

    $this->info(($dryRun ? '[dry-run] ' : '').'Import manifest written for batch '.$batchId);
    $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
  }
}
