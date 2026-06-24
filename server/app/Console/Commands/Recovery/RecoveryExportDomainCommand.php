<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryExportService;

class RecoveryExportDomainCommand extends RecoveryCommand
{
  protected $signature = 'recovery:export-domain
                            {--batch= : Recovery batch UUID}
                            {--dry-run : Count rows only; do not write bundles (default)}
                            {--execute : Write domain bundles and export manifest}';

  protected $description = 'Export domain bundles from recovery source connection';

  public function handle(RecoveryExportService $exporter, RecoveryBatchStorage $storage): int
  {
    $dryRun = $this->resolveDryRun((bool) $this->option('execute'));

    try {
      $this->guard(! $dryRun);
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    $batchId = $storage->resolveBatchId($this->option('batch'));
    $manifest = $exporter->exportDomain($batchId, $this->sourceConnection(), $dryRun);

    if (! $dryRun) {
      $storage->writeJson($batchId, 'export_manifest.json', $manifest);
    }

    $this->info(($dryRun ? '[dry-run] ' : '').'Export complete for batch '.$batchId);
    $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
  }
}
