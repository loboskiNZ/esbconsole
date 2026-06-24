<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryRollbackService;
use App\Services\Recovery\RecoveryVerifyService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RecoveryReportGenerationTest extends TestCase
{
  private string $batchId = 'report-batch-001';

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

  protected function tearDown(): void
  {
    File::deleteDirectory(storage_path('recovery/'.$this->batchId));
    parent::tearDown();
  }

  public function test_verification_report_includes_version_and_schema(): void
  {
    $storage = app(RecoveryBatchStorage::class);
    $storage->writeJson($this->batchId, 'entity_map.json', [
      'version' => 1,
      'schema' => 'esb.recovery.entity_map/v1',
      'entries' => [],
    ]);

    $report = app(RecoveryVerifyService::class)->verify(
      $this->batchId,
      'recovery_source',
      'recovery_target',
    );

    $this->assertSame(1, $report['version']);
    $this->assertSame('esb.recovery.verification_report/v1', $report['schema']);
    $this->assertArrayHasKey('row_counts', $report);
  }

  public function test_rollback_report_is_non_destructive(): void
  {
    $report = app(RecoveryRollbackService::class)->buildRollbackReport(
      $this->batchId,
      'recovery_target',
      dryRun: true,
    );

    $this->assertSame(1, $report['version']);
    $this->assertFalse($report['executed']);
    $this->assertTrue($report['dry_run']);
    $this->assertNotEmpty($report['instructions']);
  }
}
