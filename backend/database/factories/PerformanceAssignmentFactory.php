<?php

namespace Database\Factories;

use App\Models\InstrumentPart;
use App\Models\Musician;
use App\Models\Performance;
use App\Models\PerformanceAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceAssignment>
 */
class PerformanceAssignmentFactory extends Factory
{
    protected $model = PerformanceAssignment::class;

    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'musician_id' => Musician::factory(),
            'instrument_part_id' => InstrumentPart::factory(),
            'song_id' => null,
            'cue_id' => null,
            'active' => true,
        ];
    }
}
