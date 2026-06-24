<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryFileManifestService;

class RecoveryUploadFilesCommand extends RecoveryCommand
{
  protected $signature = 'recovery:upload-files
                            {--batch= : Recovery batch UUID (required)}
                            {--dry-run : Generate upload plan only (default)}
                            {--execute : Blocked in PH067A — Spaces not connected}';

  protected $description = 'Generate upload plan from file manifest (local-only; no Spaces in PH067A)';

  public function handle(RecoveryFileManifestService $files, RecoveryBatchStorage $storage): int
  {
    if ($this->option('execute')) {
      $this->error('PH067A does not connect to Spaces. Use --dry-run to generate upload plans only.');

      return self::FAILURE;
    }

    try {
      $this->guard();
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    if ($this->option('batch') === null) {
      $this->error('--batch is required for recovery:upload-files');

      return self::FAILURE;
    }

    $batchId = $storage->resolveBatchId($this->option('batch'));
    $plan = $files->buildUploadPlan($batchId);
    $storage->writeJson($batchId, 'upload_plan.json', $plan);

    $this->info('[dry-run] Upload plan generated for batch '.$batchId);
    $this->line(json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
  }
}
