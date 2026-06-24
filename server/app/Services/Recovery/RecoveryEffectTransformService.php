<?php

namespace App\Services\Recovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RecoveryEffectTransformService
{
  /** @var array<int, int> */
  private array $libraryItemToEffect = [];

  /** @var array<int, int> */
  private array $libraryParameterToEffectParameter = [];

  /** @var list<array<string, mixed>> */
  private array $operatorReview = [];

  private int $transformed = 0;

  private int $skipped = 0;

  private int $ambiguous = 0;

  public function __construct(
    private readonly RecoveryBatchStorage $storage,
  ) {}

  public function warmMaps(string $sourceConnection): void
  {
    if (! Schema::connection($sourceConnection)->hasTable('effect_library_items')
      || ! Schema::connection($sourceConnection)->hasTable('effects')) {
      return;
    }

    $libraryItems = DB::connection($sourceConnection)->table('effect_library_items')->get();
    foreach ($libraryItems as $item) {
      $matches = DB::connection($sourceConnection)
        ->table('effects')
        ->where('x32_algorithm_id', $item->x32_algorithm_id)
        ->where('x32_slot_group', $item->x32_slot_group)
        ->pluck('id')
        ->all();

      if (count($matches) === 1) {
        $this->libraryItemToEffect[(int) $item->id] = (int) $matches[0];
      } elseif (count($matches) > 1) {
        $this->ambiguous++;
        $this->operatorReview[] = [
          'type' => 'effect_library_item',
          'source_id' => (int) $item->id,
          'reason' => 'multiple_effects_match_x32',
          'candidate_effect_ids' => $matches,
        ];
      }
    }

    if (Schema::connection($sourceConnection)->hasTable('effect_library_parameters')
      && Schema::connection($sourceConnection)->hasTable('effect_parameters')) {
      $libraryParams = DB::connection($sourceConnection)->table('effect_library_parameters')->get();
      foreach ($libraryParams as $param) {
        $effectId = $this->libraryItemToEffect[(int) $param->effect_library_item_id] ?? null;
        if ($effectId === null) {
          continue;
        }

        $match = DB::connection($sourceConnection)
          ->table('effect_parameters')
          ->where('effect_id', $effectId)
          ->where('parameter_number', $param->parameter_number)
          ->value('id');

        if ($match !== null) {
          $this->libraryParameterToEffectParameter[(int) $param->id] = (int) $match;
        }
      }
    }
  }

  /** @param  array<string, mixed>  $row */
  public function transformRow(string $table, array $row): array
  {
    if ($table === 'effect_package_items') {
      $libraryId = isset($row['effect_library_item_id']) ? (int) $row['effect_library_item_id'] : null;
      if ($libraryId !== null) {
        $mapped = $this->libraryItemToEffect[$libraryId] ?? null;
        if ($mapped !== null) {
          $row['effect_id'] = $row['effect_id'] ?? $mapped;
          $this->transformed++;
        } else {
          $this->skipped++;
        }
      }
      unset($row['effect_library_item_id']);
    }

    if ($table === 'effect_package_item_parameters') {
      $libraryParamId = $row['source_effect_library_parameter_id'] ?? null;
      if ($libraryParamId !== null) {
        $mapped = $this->libraryParameterToEffectParameter[(int) $libraryParamId] ?? null;
        if ($mapped !== null) {
          $row['source_effect_parameter_id'] = $row['source_effect_parameter_id'] ?? $mapped;
          $this->transformed++;
        } else {
          $this->skipped++;
        }
      }
      unset($row['source_effect_library_parameter_id']);
    }

    return $row;
  }

  /** @return array<string, mixed> */
  public function buildReport(string $batchId): array
  {
    $report = [
      'version' => 1,
      'schema' => 'esb.recovery.effect_transform_report/v1',
      'batch_id' => $batchId,
      'generated_at' => now()->toIso8601String(),
      'transformed_count' => $this->transformed,
      'skipped_count' => $this->skipped,
      'ambiguous_count' => $this->ambiguous,
      'operator_review' => $this->operatorReview,
      'library_item_map_size' => count($this->libraryItemToEffect),
      'library_parameter_map_size' => count($this->libraryParameterToEffectParameter),
    ];

    $this->storage->writeJson($batchId, 'effect_transform_report.json', $report);

    return $report;
  }

  public function resetCounters(): void
  {
    $this->transformed = 0;
    $this->skipped = 0;
    $this->ambiguous = 0;
    $this->operatorReview = [];
  }
}
