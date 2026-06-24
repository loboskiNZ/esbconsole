<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RecoveryBatchStorageTest extends TestCase
{
  private string $batchId = 'test-batch-001';

  protected function tearDown(): void
  {
    File::deleteDirectory(storage_path('recovery/'.$this->batchId));
    parent::tearDown();
  }

  public function test_creates_batch_directory_and_writes_json(): void
  {
    $storage = app(RecoveryBatchStorage::class);
    $path = $storage->ensureBatchDirectory($this->batchId);

    $this->assertDirectoryExists($path);
    $this->assertDirectoryExists($path.'/domains');

    $payload = ['version' => 1, 'schema' => 'esb.recovery.export_manifest/v1', 'batch_id' => $this->batchId];
    $written = $storage->writeJson($this->batchId, 'export_manifest.json', $payload);

    $this->assertFileExists($written);
    $read = $storage->readJson($this->batchId, 'export_manifest.json');
    $this->assertSame(1, $read['version']);
    $this->assertSame($this->batchId, $read['batch_id']);
  }
}
