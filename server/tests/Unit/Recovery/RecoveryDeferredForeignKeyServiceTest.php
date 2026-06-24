<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryDeferredForeignKeyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecoveryDeferredForeignKeyServiceTest extends TestCase
{
  private string $batchId = 'deferred-fk-batch';

  protected function tearDown(): void
  {
    File::deleteDirectory(storage_path('recovery/'.$this->batchId));
    parent::tearDown();
  }

  public function test_captures_and_writes_deferred_fk_manifest(): void
  {
    $storage = app(RecoveryBatchStorage::class);
    $storage->ensureBatchDirectory($this->batchId);
    File::put(
      $storage->domainBundlePath($this->batchId, 'bands'),
      json_encode(['id' => 1, 'public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'primary_director_musician_id' => 3])."\n",
    );

    $service = app(RecoveryDeferredForeignKeyService::class);
    $entries = $service->captureFromBandsBundle($this->batchId);
    $manifest = $service->writeManifest($this->batchId, $entries);

    $this->assertCount(1, $entries);
    $this->assertSame('musicians', $entries[0]['referenced_table']);
    $this->assertFileExists(storage_path('recovery/'.$this->batchId.'/deferred_fk.json'));
    $this->assertSame(1, $manifest['version']);
  }

  public function test_replays_deferred_fk_using_entity_map(): void
  {
    if (! Schema::hasTable('bands')) {
      Schema::create('bands', function ($table) {
        $table->id();
        $table->uuid('public_id')->nullable();
        $table->unsignedBigInteger('primary_director_musician_id')->nullable();
        $table->timestamps();
      });
    }

    DB::table('bands')->insert(['id' => 1, 'public_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'primary_director_musician_id' => null]);
    DB::table('bands')->where('id', 1)->update(['primary_director_musician_id' => null]);

    $service = app(RecoveryDeferredForeignKeyService::class);
    $service->writeManifest($this->batchId, [[
      'table' => 'bands',
      'column' => 'primary_director_musician_id',
      'row_source_id' => 1,
      'referenced_table' => 'musicians',
      'referenced_source_id' => 3,
      'status' => 'queued',
    ]]);

    $report = $service->replay($this->batchId, 'sqlite', [
      'bands' => [1 => 1],
      'musicians' => [3 => 9],
    ]);

    $this->assertSame(1, $report['applied']);
    $this->assertSame(0, $report['unresolved']);
    $this->assertTrue($report['complete']);
    $this->assertSame(9, (int) DB::table('bands')->where('id', 1)->value('primary_director_musician_id'));
  }

  public function test_defers_band_director_column_on_import(): void
  {
    $service = app(RecoveryDeferredForeignKeyService::class);
    $row = $service->deferBandDirectorColumn(['id' => 1, 'primary_director_musician_id' => 5, 'name' => 'Band']);

    $this->assertNull($row['primary_director_musician_id']);
    $this->assertSame('Band', $row['name']);
  }
}
