<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryTransformService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RecoveryTransformServiceTest extends TestCase
{
  private string $batchId = 'transform-batch-001';

  protected function tearDown(): void
  {
    File::deleteDirectory(storage_path('recovery/'.$this->batchId));
    parent::tearDown();
  }

  public function test_detects_duplicate_public_ids(): void
  {
    $storage = app(RecoveryBatchStorage::class);
    $storage->ensureBatchDirectory($this->batchId);

    $duplicateId = '11111111-1111-1111-1111-111111111111';
    $lines = [
      json_encode(['id' => 1, 'public_id' => $duplicateId, 'name' => 'A']),
      json_encode(['id' => 2, 'public_id' => $duplicateId, 'name' => 'B']),
    ];
    File::put($storage->domainBundlePath($this->batchId, 'bands'), implode("\n", $lines)."\n");

    $result = app(RecoveryTransformService::class)->transform($this->batchId, 'sqlite');

    $this->assertSame(1, $result['version']);
    $this->assertCount(2, $result['entries']);
    $this->assertCount(1, $result['duplicate_public_ids']);
    $this->assertSame($duplicateId, $result['duplicate_public_ids'][0]['public_id']);

    $saved = $storage->readJson($this->batchId, 'entity_map.json');
    $this->assertSame('esb.recovery.entity_map/v1', $saved['schema']);
    $this->assertFileExists(storage_path('recovery/'.$this->batchId.'/deferred_fk.json'));
  }
}
