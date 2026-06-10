<?php

namespace Database\Factories;

use App\Models\Assignment;
use App\Models\InstrumentPart;
use App\Models\Musician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
    protected $model = Assignment::class;

    public function definition(): array
    {
        return [
            'musician_id' => Musician::factory(),
            'instrument_part_id' => InstrumentPart::factory(),
            'active' => true,
        ];
    }
}
