<?php

namespace Database\Factories;

use App\Models\Capability;
use App\Models\InstrumentPart;
use App\Models\Musician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Capability>
 */
class CapabilityFactory extends Factory
{
    protected $model = Capability::class;

    public function definition(): array
    {
        return [
            'musician_id' => Musician::factory(),
            'instrument_part_id' => InstrumentPart::factory(),
        ];
    }
}
