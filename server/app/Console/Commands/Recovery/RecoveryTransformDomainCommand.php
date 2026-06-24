<?php

namespace App\Console\Commands\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryTransformService;

class RecoveryTransformDomainCommand extends RecoveryCommand
{
  protected $signature = 'recovery:transform-domain
                            {--batch= : Recovery batch UUID (required)}';

  protected $description = 'Build entity_map.json with public_id preservation and duplicate detection';

  public function handle(RecoveryTransformService $transformer, RecoveryBatchStorage $storage): int
  {
    try {
      $this->guard();
    } catch (\RuntimeException $e) {
      return $this->failBlocked($e);
    }

    if ($this->option('batch') === null) {
      $this->error('--batch is required for recovery:transform-domain');

      return self::FAILURE;
    }

    $batchId = $storage->resolveBatchId($this->option('batch'));
    $entityMap = $transformer->transform($batchId);

    $this->info('entity_map.json generated for batch '.$batchId);
    $this->line(json_encode($entityMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return self::SUCCESS;
  }
}
