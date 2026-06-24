<?php

namespace Tests\Unit\Recovery;

use App\Services\Recovery\RecoveryBatchStorage;
use App\Services\Recovery\RecoveryEffectTransformService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RecoveryEffectTransformServiceTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    Schema::dropIfExists('effect_package_item_parameters');
    Schema::dropIfExists('effect_package_items');
    Schema::dropIfExists('effect_parameters');
    Schema::dropIfExists('effect_library_parameters');
    Schema::dropIfExists('effects');
    Schema::dropIfExists('effect_library_items');

    Schema::create('effect_library_items', function ($table) {
      $table->id();
      $table->unsignedTinyInteger('x32_algorithm_id');
      $table->string('x32_slot_group');
    });
    Schema::create('effects', function ($table) {
      $table->id();
      $table->unsignedTinyInteger('x32_algorithm_id');
      $table->string('x32_slot_group');
    });
    Schema::create('effect_library_parameters', function ($table) {
      $table->id();
      $table->unsignedBigInteger('effect_library_item_id');
      $table->unsignedTinyInteger('parameter_number');
    });
    Schema::create('effect_parameters', function ($table) {
      $table->id();
      $table->unsignedBigInteger('effect_id');
      $table->unsignedTinyInteger('parameter_number');
    });
  }

  public function test_maps_library_item_to_effect_and_drops_legacy_columns(): void
  {
    DB::table('effect_library_items')->insert(['id' => 10, 'x32_algorithm_id' => 5, 'x32_slot_group' => 'fx1_4']);
    DB::table('effects')->insert(['id' => 20, 'x32_algorithm_id' => 5, 'x32_slot_group' => 'fx1_4']);

    $service = app(RecoveryEffectTransformService::class);
    $service->warmMaps('sqlite');
    $row = $service->transformRow('effect_package_items', [
      'id' => 1,
      'effect_library_item_id' => 10,
      'effect_definition_id' => 1,
    ]);

    $this->assertArrayNotHasKey('effect_library_item_id', $row);
    $this->assertSame(20, $row['effect_id']);
  }

  public function test_ambiguous_library_mapping_recorded_in_report(): void
  {
    DB::table('effect_library_items')->insert(['id' => 11, 'x32_algorithm_id' => 6, 'x32_slot_group' => 'fx5_8']);
    DB::table('effects')->insert([
      ['id' => 21, 'x32_algorithm_id' => 6, 'x32_slot_group' => 'fx5_8'],
      ['id' => 22, 'x32_algorithm_id' => 6, 'x32_slot_group' => 'fx5_8'],
    ]);

    $service = app(RecoveryEffectTransformService::class);
    $service->warmMaps('sqlite');
    $report = $service->buildReport('effect-batch');

    $this->assertGreaterThanOrEqual(1, $report['ambiguous_count']);
    $this->assertFileExists(storage_path('recovery/effect-batch/effect_transform_report.json'));
  }
}
