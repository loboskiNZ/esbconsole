<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryVerifyService;

class RecoveryVerifyCommand extends RecoveryCommand
{
  protected $signature = 'recovery:verify
                            {--batch= : Recovery batch UUID (required)}';

  protected $description = 'Generate verification_report.json (read-only checks)';

  public function handle(RecoveryVerifyService $verifier, RecoveryBatchStorage $storage): int
  {
    try {
      $this->guard();
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    if ($this->option('batch') === null) {
      $this->error('--batch is required for recovery:verify');

      return self::FAILURE;
    }

    $batchId = $storage->resolveBatchId($this->option('batch'));
    $report = $verifier->verify($batchId, $this->sourceConnection(), $this->targetConnection());

    $this->info('Verification '.($report['passed'] ? 'PASS' : 'FAIL').' for batch '.$batchId);
    $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $report['passed'] ? self::SUCCESS : self::FAILURE;
  }
}
