<?php

namespace Database\Factories;

use App\Models\ActionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionType>
 */
class ActionTypeFactory extends Factory
{
    protected $model = ActionType::class;

    public function definition(): array
    {
        $code = 'TEST_TYPE_'.strtoupper(fake()->unique()->lexify('????'));

        return [
            'code' => $code,
            'name' => 'Test Type '.fake()->word(),
            'description' => null,
        ];
    }
}
