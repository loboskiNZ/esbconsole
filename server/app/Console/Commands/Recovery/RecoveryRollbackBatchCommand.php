<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryRollbackService;

class RecoveryRollbackBatchCommand extends RecoveryCommand
{
  protected $signature = 'recovery:rollback-batch
                            {--batch= : Recovery batch UUID (required)}
                            {--dry-run : Generate rollback instructions only (default)}
                            {--execute : Destructive rollback is disabled in PH067A}';

  protected $description = 'Generate rollback_report.json with instructions (no destructive actions)';

  public function handle(RecoveryRollbackService $rollback, RecoveryBatchStorage $storage): int
  {
    if ($this->option('execute')) {
      $this->error('PH067A does not execute destructive rollback. Use --dry-run to generate rollback_report.json.');

      return self::FAILURE;
    }

    try {
      $this->guard();
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    if ($this->option('batch') === null) {
      $this->error('--batch is required for recovery:rollback-batch');

      return self::FAILURE;
    }

    $batchId = $storage->resolveBatchId($this->option('batch'));
    $report = $rollback->buildRollbackReport($batchId, $this->targetConnection(), true);

    $this->info('[dry-run] Rollback report generated for batch '.$batchId);
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
  }
}
