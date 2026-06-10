<?php

namespace Database\Factories;

use App\Models\Chart;
use App\Models\SongInstrumentPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chart>
 */
class ChartFactory extends Factory
{
    protected $model = Chart::class;

    public function definition(): array
    {
        return [
            'song_instrument_part_id' => SongInstrumentPart::factory(),
            'title' => 'Test Chart '.fake()->word(),
            'storage_reference' => 'local-demo/charts/'.fake()->uuid().'.pdf',
            'checksum' => fake()->sha256(),
            'notes' => null,
        ];
    }
}
