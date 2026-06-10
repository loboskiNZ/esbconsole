<?php

namespace Database\Factories;

use App\Models\Performance;
use App\Models\Readiness;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Readiness>
 */
class ReadinessFactory extends Factory
{
    protected $model = Readiness::class;

    public function definition(): array
    {
        return [
            'performance_id' => Performance::factory(),
            'status' => Readiness::STATUS_NOT_READY,
            'notes' => null,
        ];
    }
}
