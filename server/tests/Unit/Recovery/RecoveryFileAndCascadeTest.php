<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryCascadeGuardService;
use App\Services\Recovery\RecoveryFileResolutionService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RecoveryFileAndCascadeTest extends TestCase
{
  private string $tempRoot;

  protected function setUp(): void
  {
    parent::setUp();
    $this->tempRoot = storage_path('framework/testing/recovery-files');
    File::ensureDirectoryExists($this->tempRoot);
    config(['recovery.storage_roots.chart' => $this->tempRoot]);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->tempRoot);
    File::deleteDirectory(storage_path('recovery/file-batch'));
    parent::tearDown();
  }

  public function test_resolves_file_under_configured_root(): void
  {
    $chartPath = $this->tempRoot.'/sample.pdf';
    File::put($chartPath, 'pdf-content');

    $service = app(RecoveryFileResolutionService::class);
    $result = $service->resolve('sample.pdf', 'charts', true);

    $this->assertSame('resolved', $result['resolution_class']);
    $this->assertSame($chartPath, $result['path']);
  }

  public function test_classifies_forge_absolute_path_as_path_mismatch(): void
  {
    $service = app(RecoveryFileResolutionService::class);
    $result = $service->resolve('/home/forge/band.edandtheshadowboys.com/storage/charts/x.pdf', 'charts', true);

    $this->assertSame('path_mismatch', $result['resolution_class']);
    $this->assertNull($result['path']);
  }

  public function test_classifies_required_missing_when_not_found(): void
  {
    $service = app(RecoveryFileResolutionService::class);
    $result = $service->resolve('does-not-exist.pdf', 'charts', true);

    $this->assertSame('required_missing', $result['resolution_class']);
  }

  public function test_cascade_guard_blocks_dependent_domains_when_bands_fail(): void
  {
    $guard = app(RecoveryCascadeGuardService::class);
    $guard->recordBandImportResult(expected: 8, inserted: 7, skipped: 1, errors: ['bands:1:fk']);

    $this->assertTrue($guard->isBlocked('songs'));
    $this->assertTrue($guard->isBlocked('charts'));
    $this->assertFalse($guard->isBlocked('musicians'));

    $report = $guard->buildReport('cascade-batch');
    $this->assertTrue($report['bands_blocked']);
    $this->assertContains('songs', $report['blocked_domains']);
  }
}
