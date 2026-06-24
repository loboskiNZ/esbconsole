<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryVerifyService;
use Tests\TestCase;

class RecoveryVerifyServiceV2Test extends TestCase
{
  private string $batchId = 'verify-v2-batch';

  protected function setUp(): void
  {
    parent::setUp();

    config([
      'recovery.source_connection' => 'recovery_source',
      'recovery.target_connection' => 'recovery_target',
      'database.connections.recovery_source' => config('database.connections.sqlite'),
      'database.connections.recovery_target' => config('database.connections.sqlite'),
    ]);
  }

  public function test_verification_report_includes_v2_sections(): void
  {
    $storage = app(RecoveryBatchStorage::class);
    $storage->writeJson($this->batchId, 'entity_map.json', [
      'version' => 1,
      'entries' => [['public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'cloud_id' => 1]],
    ]);
    $storage->writeJson($this->batchId, 'deferred_fk.json', ['entries' => []]);
    $storage->writeJson($this->batchId, 'deferred_fk_report.json', [
      'applied' => 0,
      'unresolved' => 0,
      'complete' => true,
    ]);
    $storage->writeJson($this->batchId, 'missing_files_report.json', [
      'summary' => ['required_missing' => 0],
      'by_class' => ['required_missing' => []],
    ]);
    $storage->writeJson($this->batchId, 'file_manifest.json', ['files' => []]);

    $report = app(RecoveryVerifyService::class)->verify(
      $this->batchId,
      'recovery_source',
      'recovery_target',
    );

    $this->assertSame(2, $report['version']);
    $this->assertArrayHasKey('deferred_fk', $report);
    $this->assertArrayHasKey('effect_transform', $report);
    $this->assertArrayHasKey('file_resolution', $report);
    $this->assertArrayHasKey('gate4_readiness', $report);
    $this->assertSame('esb.recovery.verification_report/v2', $report['schema']);
  }
}
