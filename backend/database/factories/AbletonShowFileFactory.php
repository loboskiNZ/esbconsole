<?php

namespace Database\Factories;

use App\Models\AbletonShowFile;
use App\Models\Band;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AbletonShowFile>
 */
class AbletonShowFileFactory extends Factory
{
    protected $model = AbletonShowFile::class;

    public function definition(): array
    {
        return [
            'band_id' => Band::factory(),
            'name' => 'Test Ableton Show '.fake()->unique()->word(),
            'storage_reference' => 'local-demo/ableton/'.fake()->uuid().'.als',
            'checksum' => fake()->sha256(),
            'notes' => null,
        ];
    }
}
