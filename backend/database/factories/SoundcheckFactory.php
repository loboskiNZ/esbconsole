<?php

namespace Database\Factories;

use App\Models\Performance;
use App\Models\Soundcheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Soundcheck>
 */
class SoundcheckFactory extends Factory
{
    protected $model = Soundcheck::class;

    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'status' => Soundcheck::STATUS_NOT_STARTED,
            'notes' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }
}
