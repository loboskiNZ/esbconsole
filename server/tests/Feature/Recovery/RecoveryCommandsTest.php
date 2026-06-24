<?php

namespace Tests\Feature\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class RecoveryCommandsTest extends TestCase
{
  private string $batchId = 'feature-recovery-batch';

  protected function setUp(): void
  {
    parent::setUp();

    config([
      'app.env' => 'local',
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

  public function test_recovery_commands_are_registered(): void
  {
    $commands = collect(Artisan::all())->keys()->filter(fn (string $name) => str_starts_with($name, 'recovery:'));

    $expected = [
      'recovery:plan',
      'recovery:export-domain',
      'recovery:export-files',
      'recovery:transform-domain',
      'recovery:import-domain',
      'recovery:upload-files',
      'recovery:verify',
      'recovery:rollback-batch',
    ];

    foreach ($expected as $command) {
      $this->assertTrue($commands->contains($command), "Missing command: {$command}");
    }
  }

  public function test_export_domain_dry_run_does_not_write_manifest(): void
  {
    $exit = Artisan::call('recovery:export-domain', [
      '--batch' => $this->batchId,
      '--dry-run' => true,
    ]);

    $this->assertSame(0, $exit);
    $this->assertFileDoesNotExist(storage_path('recovery/'.$this->batchId.'/export_manifest.json'));
  }

  public function test_pipeline_generates_manifests_in_dry_run_mode(): void
  {
    $storage = app(RecoveryBatchStorage::class);
    $storage->ensureBatchDirectory($this->batchId);
    File::put(
      $storage->domainBundlePath($this->batchId, 'bands'),
      json_encode(['id' => 1, 'public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'name' => 'Band'])."\n",
    );

    $this->assertSame(0, Artisan::call('recovery:transform-domain', ['--batch' => $this->batchId]));
    $this->assertFileExists(storage_path('recovery/'.$this->batchId.'/entity_map.json'));

    $this->assertSame(0, Artisan::call('recovery:import-domain', ['--batch' => $this->batchId]));
    $import = $storage->readJson($this->batchId, 'import_manifest.json');
    $this->assertTrue($import['dry_run']);

    $this->assertSame(0, Artisan::call('recovery:export-files', ['--batch' => $this->batchId]));
    $this->assertFileExists(storage_path('recovery/'.$this->batchId.'/file_manifest.json'));

    $this->assertSame(0, Artisan::call('recovery:upload-files', ['--batch' => $this->batchId]));
    $this->assertFileExists(storage_path('recovery/'.$this->batchId.'/upload_plan.json'));

    $this->assertSame(0, Artisan::call('recovery:verify', ['--batch' => $this->batchId]));
    $verify = $storage->readJson($this->batchId, 'verification_report.json');
    $this->assertSame(1, $verify['version']);

    $this->assertSame(0, Artisan::call('recovery:rollback-batch', ['--batch' => $this->batchId]));
    $rollback = $storage->readJson($this->batchId, 'rollback_report.json');
    $this->assertFalse($rollback['executed']);
    $this->assertTrue($rollback['dry_run']);
  }

  public function test_upload_execute_is_blocked_in_ph067a(): void
  {
    $storage = app(RecoveryBatchStorage::class);
    $storage->writeJson($this->batchId, 'file_manifest.json', [
      'version' => 1,
      'schema' => 'esb.recovery.files_manifest/v1',
      'batch_id' => $this->batchId,
      'files' => [],
    ]);

    $exit = Artisan::call('recovery:upload-files', [
      '--batch' => $this->batchId,
      '--execute' => true,
    ]);

    $this->assertSame(1, $exit);
  }
}
