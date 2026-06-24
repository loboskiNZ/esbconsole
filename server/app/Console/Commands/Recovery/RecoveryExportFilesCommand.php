<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryFileManifestService;

class RecoveryExportFilesCommand extends RecoveryCommand
{
  protected $signature = 'recovery:export-files
                            {--batch= : Recovery batch UUID (required)}
                            {--dry-run : Generate manifest only (default)}';

  protected $description = 'Generate file manifest from exported domain bundles (no uploads)';

  public function handle(RecoveryFileManifestService $files, RecoveryBatchStorage $storage): int
  {
    try {
      $this->guard();
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    $batchId = $storage->resolveBatchId($this->option('batch'));
    if ($this->option('batch') === null) {
      $this->error('--batch is required for recovery:export-files');

      return self::FAILURE;
    }

    $manifest = $files->buildFileManifest($batchId, $this->sourceConnection(), true);
    $storage->writeJson($batchId, 'file_manifest.json', $manifest);

    $this->info('[dry-run] File manifest written for batch '.$batchId);
    $this->line(json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
  }
}
