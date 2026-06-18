<?php

namespace Database\Factories;

use App\Enums\FallbackConsoleRecallType;
use App\Enums\SongEffectAssignmentType;
use App\Models\EffectPackage;
use App\Models\Song;
use App\Models\SongEffectAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SongEffectAssignment>
 */
class SongEffectAssignmentFactory extends Factory
{
    protected $model = SongEffectAssignment::class;

    public function definition(): array
    {
        return [
            'song_id' => Song::factory(),
            'effect_package_id' => EffectPackage::factory(),
            'priority' => 100,
            'assignment_type' => SongEffectAssignmentType::SongSpecific,
            'enabled' => true,
            'fallback_console_recall_name' => null,
            'fallback_console_recall_type' => null,
            'notes' => null,
        ];
    }

    public function withFallbackRecall(string $name, FallbackConsoleRecallType $type): static
    {
        return $this->state(fn (): array => [
            'fallback_console_recall_name' => $name,
            'fallback_console_recall_type' => $type,
        ]);
    }
}
